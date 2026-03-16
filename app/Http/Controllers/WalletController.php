<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AdminSetting;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\VirtualGift;
use App\Models\WalletPackage;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\ApiCacheService;
use App\Services\LevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;

class WalletController extends Controller
{
    public function __construct(
        private LevelService $levelService,
        private ApiCacheService $cache
    ) {}

    /**
     * GET /api/v1/wallet/packages – active wallet packages. Cached.
     */
    public function packages(Request $request)
    {
        $ttl = $this->cache->ttl('catalog');
        $data = $this->cache->remember($this->cache->versionedKey('wallet_packages'), $ttl, function () {
            $packages = WalletPackage::where('is_active', true)
                ->orderBy('price_in_inr')
                ->get(['id', 'coin_amount', 'price_in_inr']);
            return [
                'packages' => $packages->map(fn ($p) => [
                    'id' => $p->id,
                    'coin_amount' => (int) $p->coin_amount,
                    'price_in_inr' => (float) $p->price_in_inr,
                ])->values()->all(),
            ];
        });
        return $this->cache->applyHttpCacheHeaders($request, ApiResponse::success($data), $ttl, 'public');
    }

    /**
     * POST /api/v1/wallet/recharge/initiate – create Razorpay order and PENDING wallet_transaction.
     * Body: { package_id }.
     */
    public function initiateRecharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'package_id' => 'required|integer|exists:wallet_packages,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $package = WalletPackage::where('is_active', true)->findOrFail($validated['package_id']);
        $amountInr = (float) $package->price_in_inr;
        $amountPaise = (int) round($amountInr * 100);

        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');
        if (empty($keyId) || empty($keySecret)) {
            return ApiResponse::error('CONFIG_ERROR', 'Razorpay is not configured', 503);
        }

        try {
            $api = new Api($keyId, $keySecret);
            $order = $api->order->create([
                'receipt' => 'wallet_' . $user->id . '_' . time(),
                'amount' => $amountPaise,
                'currency' => 'INR',
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('RAZORPAY_ERROR', $e->getMessage(), 502);
        }

        $razorpayOrderId = $order->id;
        WalletTransaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'razorpay_order_id' => $razorpayOrderId,
            'status' => WalletTransaction::STATUS_PENDING,
            'amount_paid_inr' => $amountInr,
            'coins_credited' => $package->coin_amount,
        ]);

        return ApiResponse::success([
            'key_id' => $keyId,
            'razorpay_order_id' => $razorpayOrderId,
            'amount_in_paise' => $amountPaise,
            'coins_credited' => (int) $package->coin_amount,
        ]);
    }

    /**
     * POST /api/v1/wallet/recharge/verify – verify Razorpay signature, update transaction, credit wallet.
     * Body: razorpay_order_id, razorpay_payment_id, razorpay_signature.
     */
    public function verifyRecharge(Request $request)
    {
        try {
            $validated = $request->validate([
                'razorpay_order_id' => 'required|string|max:255',
                'razorpay_payment_id' => 'required|string|max:255',
                'razorpay_signature' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $keySecret = config('services.razorpay.key_secret');
        if (empty($keySecret)) {
            return ApiResponse::error('CONFIG_ERROR', 'Razorpay is not configured', 503);
        }

        $orderId = $validated['razorpay_order_id'];
        $paymentId = $validated['razorpay_payment_id'];
        $signature = $validated['razorpay_signature'];

        $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
        if (!hash_equals($generated, $signature)) {
            return ApiResponse::error('INVALID_SIGNATURE', 'Payment signature verification failed', 400);
        }

        $wt = WalletTransaction::where('razorpay_order_id', $orderId)->first();
        if (!$wt) {
            return ApiResponse::notFound('Order not found');
        }
        if ($wt->status === WalletTransaction::STATUS_SUCCESS) {
            return ApiResponse::success([
                'status' => 'already_credited',
                'wallet_balance' => (int) $wt->user->wallet_balance,
                'current_balance' => (int) $wt->user->wallet_balance,
            ]);
        }

        DB::transaction(function () use ($wt, $orderId, $paymentId, $signature) {
            $wt->update([
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => WalletTransaction::STATUS_SUCCESS,
            ]);
            $wt->user->increment('wallet_balance', $wt->coins_credited);
        });

        $wt->user->refresh();
        return ApiResponse::success([
            'status' => 'success',
            'coins_credited' => (int) $wt->coins_credited,
            'wallet_balance' => (int) $wt->user->wallet_balance,
            'current_balance' => (int) $wt->user->wallet_balance,
        ]);
    }

    /**
     * POST /api/v1/wallet/transfer – Seller or admin transfers coins to another user.
     * Auth: Bearer token. Only role seller or admin may call.
     * Body: receiver_id (user id), coin_amount (int), note (optional).
     */
    public function transfer(Request $request)
    {
        try {
            $validated = $request->validate([
                'receiver_id' => 'required|integer|exists:users,id',
                'coin_amount' => 'required|integer|min:1',
                'note' => 'nullable|string|max:500',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $sender = $request->user();
        $receiverId = (int) $validated['receiver_id'];
        $coinAmount = (int) $validated['coin_amount'];

        if (!in_array($sender->role ?? 'user', [User::ROLE_SELLER, User::ROLE_ADMIN], true)) {
            return ApiResponse::forbidden('Only sellers or admins can transfer coins.');
        }

        if ($receiverId === $sender->id) {
            return ApiResponse::error('INVALID_REQUEST', 'You cannot transfer coins to yourself.', 400);
        }

        $receiver = User::find($receiverId);
        if (!$receiver) {
            return ApiResponse::notFound('Receiver not found.');
        }

        $senderBalance = (int) ($sender->wallet_balance ?? $sender->coin_balance ?? 0);
        if ($senderBalance < $coinAmount) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_FUNDS',
                    'message' => 'You do not have enough coins for this transfer.',
                ],
            ], 400);
        }

        try {
            $result = DB::transaction(function () use ($sender, $receiverId, $coinAmount) {
                $s = User::where('id', $sender->id)->lockForUpdate()->first();
                $r = User::where('id', $receiverId)->lockForUpdate()->first();
                if (!$s || !$r) {
                    throw new \RuntimeException('User not found');
                }
                $s->decrement('wallet_balance', $coinAmount);
                // Transfer is Gold Coins only: credit receiver's wallet (spendable coins), not Gems.
                // Gems are earned only via call/end and gifts (wallet/send-gift, rooms/.../gifts/send).
                $r->increment('wallet_balance', $coinAmount);

                $tx = CoinTransaction::create([
                    'sender_id' => $s->id,
                    'receiver_id' => $r->id,
                    'transaction_type' => CoinTransaction::TYPE_SELLER_TRANSFER,
                    'reference_id' => null,
                    'gross_coins_deducted' => $coinAmount,
                    'admin_commission_coins' => 0,
                    'net_coins_received' => $coinAmount,
                ]);

                $s->refresh();
                $r->refresh();
                return [
                    'transaction_id' => 'TRX_' . $tx->id,
                    'sender_balance_after' => (int) $s->wallet_balance,
                    'receiver_id' => (string) $r->id,
                    'coin_amount' => $coinAmount,
                    'receiver_wallet_balance_after' => (int) $r->wallet_balance,
                ];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'User not found') {
                return ApiResponse::notFound('Receiver not found.');
            }
            throw $e;
        }

        return ApiResponse::success($result);
    }

    /**
     * GET /api/v1/wallet/transactions – unified list (recharges + coin_transactions). Paginated.
     * Query: page (default 1), limit (default 20, max 100). Sorted by created_at DESC.
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $walletTxns = WalletTransaction::where('user_id', $userId)->get();
        $coinTxns = CoinTransaction::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender:id,name,display_name', 'receiver:id,name,display_name'])
            ->get();

        $list = [];

        foreach ($walletTxns as $wt) {
            $list[] = [
                'id' => 'recharge_' . $wt->id,
                'type' => 'recharge',
                'title' => 'Wallet Recharge',
                'coin_amount' => (int) $wt->coins_credited,
                'status' => strtolower($wt->status),
                'created_at' => $wt->created_at?->toIso8601String(),
                '_sort_ts' => $wt->created_at?->timestamp ?? 0,
            ];
        }

        foreach ($coinTxns as $ct) {
            if ($ct->transaction_type === CoinTransaction::TYPE_CALL_COMMISSION && $ct->sender_id === $userId) {
                continue;
            }
            $other = $ct->sender_id === $userId ? $ct->receiver : $ct->sender;
            $otherName = $other ? ($other->display_name ?? $other->name ?? 'User') : 'User';

            if ($ct->sender_id === $userId) {
                $coinAmount = - (int) $ct->gross_coins_deducted;
                if ($ct->transaction_type === CoinTransaction::TYPE_GIFT) {
                    $title = 'Gift to ' . $otherName;
                    $type = 'gift';
                } elseif ($ct->transaction_type === CoinTransaction::TYPE_WITHDRAWAL) {
                    $title = 'Withdrawal request';
                    $type = 'withdrawal';
                } elseif ($ct->transaction_type === CoinTransaction::TYPE_SELLER_TRANSFER) {
                    $title = 'Transfer to ' . $otherName;
                    $type = 'transfer';
                } else {
                    $callLabel = $ct->transaction_type === CoinTransaction::TYPE_VIDEO_CALL ? 'Video call' : 'Audio call';
                    $title = $callLabel . ' with ' . $otherName;
                    $type = 'call';
                }
            } else {
                $coinAmount = (int) $ct->net_coins_received;
                if ($ct->transaction_type === CoinTransaction::TYPE_GIFT) {
                    $title = 'Gift from ' . $otherName;
                    $type = 'gift';
                } elseif ($ct->transaction_type === CoinTransaction::TYPE_CALL_COMMISSION) {
                    $title = 'Call commission';
                    $type = 'commission';
                } elseif ($ct->transaction_type === CoinTransaction::TYPE_SELLER_TRANSFER) {
                    $title = 'Transfer from ' . $otherName;
                    $type = 'transfer';
                } else {
                    $callLabel = $ct->transaction_type === CoinTransaction::TYPE_VIDEO_CALL ? 'Video call' : 'Audio call';
                    $title = $callLabel . ' with ' . $otherName;
                    $type = 'call';
                }
            }

            $list[] = [
                'id' => 'coin_' . $ct->id,
                'type' => $type,
                'title' => $title,
                'coin_amount' => $coinAmount,
                'status' => 'completed',
                'created_at' => $ct->created_at?->toIso8601String(),
                '_sort_ts' => $ct->created_at?->timestamp ?? 0,
            ];
        }

        usort($list, function ($a, $b) {
            return ($b['_sort_ts'] ?? 0) <=> ($a['_sort_ts'] ?? 0);
        });

        $total = count($list);
        $list = array_slice($list, ($page - 1) * $limit, $limit);
        $transactions = array_map(function ($row) {
            unset($row['_sort_ts']);
            return $row;
        }, $list);

        return ApiResponse::success(
            ['transactions' => array_values($transactions)],
            ApiResponse::paginationMeta($total, $page, $limit)
        );
    }

    /**
     * GET /api/v1/gifts – active virtual gifts. Cached.
     */
    public function gifts(Request $request)
    {
        $ttl = $this->cache->ttl('catalog');
        $data = $this->cache->remember($this->cache->versionedKey('gifts'), $ttl, function () {
            $gifts = VirtualGift::where('is_active', true)
                ->orderBy('coin_cost')
                ->get(['id', 'name', 'image_url', 'animation_url', 'coin_cost']);
            return [
                'gifts' => $gifts->map(fn ($g) => [
                    'id' => (string) $g->id,
                    'name' => $g->name,
                    'coin_cost' => (int) $g->coin_cost,
                    'image_url' => $g->image_url,
                    'animation_url' => $g->animation_url,
                ])->values()->all(),
            ];
        });
        return $this->cache->applyHttpCacheHeaders($request, ApiResponse::success($data), $ttl, 'public');
    }

    /**
     * POST /api/v1/wallet/send-gift – send virtual gift to receiver (deduct sender, credit receiver after commission).
     * Body: gift_id, receiver_id.
     */
    public function sendGift(Request $request)
    {
        try {
            $validated = $request->validate([
                'gift_id' => 'required|integer|exists:virtual_gifts,id',
                'receiver_id' => 'required|integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $sender = $request->user();
        $receiverId = (int) $validated['receiver_id'];
        if ($receiverId === $sender->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot send gift to yourself', 400);
        }

        $gift = VirtualGift::where('is_active', true)->findOrFail($validated['gift_id']);
        $cost = (int) $gift->coin_cost;
        $senderBalance = (int) $sender->wallet_balance;
        if ($senderBalance < $cost) {
            return ApiResponse::error('INSUFFICIENT_BALANCE', 'Insufficient wallet balance', 400);
        }

        $settings = AdminSetting::get();
        $commissionPercent = (int) $settings->gift_commission_percent;
        $adminCommission = (int) floor($cost * $commissionPercent / 100);
        $netToReceiver = $cost - $adminCommission; // Platform keeps adminCommission; receiver gets net only

        $receiver = \App\Models\User::findOrFail($receiverId);

        DB::transaction(function () use ($sender, $receiver, $gift, $cost, $adminCommission, $netToReceiver) {
            $s = User::where('id', $sender->id)->lockForUpdate()->first();
            $r = User::where('id', $receiver->id)->lockForUpdate()->first();
            if (!$s || !$r) {
                throw new \RuntimeException('User not found');
            }
            $s->decrement('wallet_balance', $cost);
            $r->increment('gems', $netToReceiver);
            $r->increment('total_earned_coins', $netToReceiver);
            CoinTransaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'transaction_type' => CoinTransaction::TYPE_GIFT,
                'reference_id' => $gift->id,
                'gross_coins_deducted' => $cost,
                'admin_commission_coins' => $adminCommission,
                'net_coins_received' => $netToReceiver,
            ]);
        });

        $sender->refresh();
        $receiver->refresh();

        $xpPerGift = 5;
        $this->levelService->addXp($sender->id, $xpPerGift);

        return ApiResponse::success([
            'gift_id' => $gift->id,
            'coin_cost' => $cost,
            'sender_balance_after' => (int) $sender->wallet_balance,
            'receiver_gems_after' => (int) $receiver->gems,
        ]);
    }

    /**
     * POST /api/v1/wallet/referral/convert – Convert referral_balance to gold coins (wallet_balance).
     * Auth required. Moves full referral_balance to wallet using referral_coin_conversion_rate (1:1 default).
     */
    public function referralConvert(Request $request)
    {
        $user = $request->user();
        $referralBalance = (int) ($user->referral_balance ?? 0);
        if ($referralBalance <= 0) {
            return ApiResponse::error('NO_REFERRAL_BALANCE', 'No referral balance to convert', 400);
        }

        $settings = AdminSetting::get();
        $rate = (int) ($settings->referral_coin_conversion_rate ?? 1);
        $totalGold = $referralBalance * $rate;

        DB::transaction(function () use ($user, $rate) {
            $u = User::where('id', $user->id)->lockForUpdate()->first();
            if (!$u) {
                throw new \RuntimeException('User not found');
            }
            $currentReferral = (int) ($u->referral_balance ?? 0);
            if ($currentReferral <= 0) {
                throw new \RuntimeException('NO_REFERRAL_BALANCE');
            }
            $coinsToAdd = $currentReferral * $rate;
            $u->referral_balance = 0;
            $u->wallet_balance = (int) ($u->wallet_balance ?? 0) + $coinsToAdd;
            $u->save();
        });

        $user->refresh();
        return ApiResponse::success([
            'referral_balance' => (int) ($user->referral_balance ?? 0),
            'wallet_balance' => (int) ($user->wallet_balance ?? 0),
            'coins' => (int) ($user->wallet_balance ?? $user->coin_balance ?? 0),
        ]);
    }

    /**
     * GET /api/v1/wallet/can-call/{receiver_id}/{call_type} – can_call and max_minutes from wallet and admin_settings.
     * call_type: audio | video.
     */
    public function canCall(Request $request, int $receiver_id, string $call_type)
    {
        if (!in_array($call_type, ['audio', 'video'], true)) {
            return ApiResponse::error('INVALID_REQUEST', 'call_type must be audio or video', 400);
        }

        $sender = $request->user();
        $balance = (int) $sender->wallet_balance;
        $settings = AdminSetting::get();
        $pricePerMin = $call_type === 'video'
            ? (int) $settings->video_call_price_per_min
            : (int) $settings->audio_call_price_per_min;

        $canCall = $pricePerMin > 0 && $balance >= $pricePerMin;
        $maxMinutes = $pricePerMin > 0 ? (int) floor($balance / $pricePerMin) : 0;

        return ApiResponse::success([
            'can_call' => $canCall,
            'max_minutes' => $maxMinutes,
            'wallet_balance' => $balance,
            'price_per_min' => $pricePerMin,
        ]);
    }

    /**
     * POST /api/v1/wallet/withdraw - Create a gems withdrawal request.
     * Body: amount (int), payment_method (string), payment_details (string), ifsc_code (optional, India),
     *       full_name, bank_name, bank_address, swift_code, country, is_international (optional, for abroad).
     */
    public function withdraw(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|integer|min:1',
                'payment_method' => 'required|string|max:50',
                'payment_details' => 'required|string|max:1000',
                'ifsc_code' => 'nullable|string|max:50',
                'full_name' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'bank_address' => 'nullable|string|max:500',
                'swift_code' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:100',
                'is_international' => 'sometimes|boolean',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $amount = (int) $validated['amount'];

        if ($amount > (int) ($user->gems ?? 0)) {
            return ApiResponse::error('INSUFFICIENT_GEMS', 'Insufficient gems', 400);
        }

        try {
            $withdrawal = DB::transaction(function () use ($user, $amount, $validated) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                if (!$u || (int) ($u->gems ?? 0) < $amount) {
                    throw new \RuntimeException('Insufficient gems');
                }
                $u->decrement('gems', $amount);

                $req = WithdrawalRequest::create([
                    'user_id' => $u->id,
                    'gems_amount' => $amount,
                    'payment_method' => $validated['payment_method'],
                    'payment_details' => $validated['payment_details'],
                    'ifsc_code' => $validated['ifsc_code'] ?? null,
                    'full_name' => $validated['full_name'] ?? null,
                    'bank_name' => $validated['bank_name'] ?? null,
                    'bank_address' => $validated['bank_address'] ?? null,
                    'swift_code' => $validated['swift_code'] ?? null,
                    'country' => $validated['country'] ?? null,
                    'is_international' => (bool) ($validated['is_international'] ?? false),
                    'status' => WithdrawalRequest::STATUS_PENDING,
                ]);

                $systemUserId = config('admin.system_user_id', 1);
                CoinTransaction::create([
                    'sender_id' => $u->id,
                    'receiver_id' => $systemUserId,
                    'transaction_type' => CoinTransaction::TYPE_WITHDRAWAL,
                    'reference_id' => $req->id,
                    'gross_coins_deducted' => $amount,
                    'admin_commission_coins' => 0,
                    'net_coins_received' => 0,
                ]);

                return $req;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Insufficient gems') {
                return ApiResponse::error('INSUFFICIENT_GEMS', 'Insufficient gems', 400);
            }
            throw $e;
        }

        $user->refresh();
        return ApiResponse::success([
            'withdrawal_id' => $withdrawal->id,
            'gems_amount' => $withdrawal->gems_amount,
            'status' => $withdrawal->status,
            'gems_balance_after' => (int) $user->gems,
        ]);
    }

    /**
     * GET /api/v1/wallet/withdrawals - List current user's withdrawal requests. Paginated.
     * Query: page (default 1), limit (default 20, max 50).
     */
    public function withdrawals(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);

        $query = WithdrawalRequest::where('user_id', $user->id)->orderBy('created_at', 'desc');
        $total = $query->count();
        $items = $query->skip(($page - 1) * $limit)->take($limit)->get();

        $list = $items->map(fn (WithdrawalRequest $r) => [
            'id' => $r->id,
            'gems_amount' => (int) $r->gems_amount,
            'payment_method' => $r->payment_method,
            'payment_details' => $r->payment_details,
            'ifsc_code' => $r->ifsc_code,
            'full_name' => $r->full_name,
            'bank_name' => $r->bank_name,
            'bank_address' => $r->bank_address,
            'swift_code' => $r->swift_code,
            'country' => $r->country,
            'is_international' => (bool) $r->is_international,
            'status' => $r->status,
            'created_at' => $r->created_at->toIso8601String(),
            'admin_note' => $r->status !== WithdrawalRequest::STATUS_PENDING ? $r->admin_note : null,
        ])->values()->all();

        return ApiResponse::success($list, ApiResponse::paginationMeta($total, $page, $limit));
    }
}

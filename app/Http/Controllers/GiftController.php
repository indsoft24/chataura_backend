<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AdminSetting;
use App\Models\GiftType;
use App\Models\Room;
use App\Models\VirtualGift;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GiftController extends Controller
{
    /**
     * GET /api/v1/gift-types – list gift types (room catalog). Cached.
     */
    public function index(ApiCacheService $cache)
    {
        $ttl = $cache->ttl('gifts');
        $data = $cache->remember($cache->versionedKey('gift_types'), $ttl, function () {
            $gifts = GiftType::where('is_active', true)
                ->orderBy('coin_price')
                ->get(['id', 'name', 'coin_price', 'image_url', 'animation_url']);

            if ($gifts->isEmpty()) {
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
            }
            return [
                'gifts' => $gifts->map(fn ($g) => [
                    'id' => (string) $g->id,
                    'name' => $g->name,
                    'coin_cost' => (int) $g->coin_price,
                    'image_url' => $g->image_url,
                    'animation_url' => $g->animation_url,
                ])->values()->all(),
            ];
        });
        return $cache->applyHttpCacheHeaders(request(), ApiResponse::success($data), $ttl, 'public');
    }

    /**
     * Send a gift. Accepts gift_id from either gift_types (uuid) or virtual_gifts (integer) so room panel can use same catalog as 1-1 chat.
     */
    public function send(Request $request, string $roomId)
    {
        try {
            $giftIdRaw = $request->input('gift_id');
            $receiverIdInput = $request->has('receiver_id') ? (int) $request->input('receiver_id') : null;
            $request->merge([
                'receiver_id' => $receiverIdInput,
                'gift_id' => is_numeric($giftIdRaw) ? (int) $giftIdRaw : $giftIdRaw,
            ]);

            $validated = $request->validate([
                'gift_id' => 'required',
                'receiver_id' => 'required|integer|exists:users,id',
                'quantity' => 'nullable|integer|min:1|max:100',
            ]);

            $room = Room::find($roomId);

            if (!$room || $room->trashed() || !$room->is_live) {
                return ApiResponse::notFound('Room not found or closed');
            }

            $sender = $request->user();
            $receiver = \App\Models\User::find($validated['receiver_id']);
            $giftType = null;
            $virtualGift = null;
            if (is_numeric($validated['gift_id'])) {
                $virtualGift = VirtualGift::where('is_active', true)->find((int) $validated['gift_id']);
            } else {
                $giftType = GiftType::where('is_active', true)->find($validated['gift_id']);
            }
            if (!$giftType && !$virtualGift) {
                return ApiResponse::notFound('Gift not found or inactive');
            }

            $quantity = $validated['quantity'] ?? 1;
            $totalCost = $giftType
                ? $giftType->coin_price * $quantity
                : $virtualGift->coin_cost * $quantity;

            // Verify receiver is in the room
            $receiverMember = \App\Models\RoomMember::where('room_id', $roomId)
                ->where('user_id', $receiver->id)
                ->whereNull('left_at')
                ->first();

            if (!$receiverMember) {
                return ApiResponse::notFound('Receiver is not in this room');
            }

            // Check sender balance (use wallet_balance as single spendable balance)
            $senderBalance = (int) ($sender->wallet_balance ?? 0);
            if ($senderBalance < $totalCost) {
                return ApiResponse::error('INSUFFICIENT_BALANCE', 'Insufficient wallet balance', 400);
            }

            // Net to receiver after admin commission (room gifts)
            $settings = AdminSetting::first();
            $commissionPercent = $settings ? (int) ($settings->gift_commission_percent ?? 0) : 0;
            $adminCommission = (int) floor($totalCost * $commissionPercent / 100);
            $receiverAmount = $totalCost - $adminCommission;

            // Atomic transaction with row locks
            DB::beginTransaction();
            try {
                $s = \App\Models\User::where('id', $sender->id)->lockForUpdate()->first();
                $r = \App\Models\User::where('id', $receiver->id)->lockForUpdate()->first();
                if (!$s || !$r) {
                    throw new \RuntimeException('User not found');
                }
                $s->decrement('wallet_balance', $totalCost);
                $r->increment('gems', $receiverAmount);
                $r->increment('total_earned_coins', $receiverAmount);

                $transactionId = null;
                if ($giftType) {
                    $transaction = Transaction::create([
                        'sender_id' => $sender->id,
                        'receiver_id' => $receiver->id,
                        'room_id' => $roomId,
                        'gift_type_id' => $giftType->id,
                        'quantity' => $quantity,
                        'coin_amount' => $totalCost,
                    ]);
                    $transactionId = $transaction->id;
                }

                DB::commit();

                $sender->refresh();
                $receiver->refresh();

                return ApiResponse::success([
                    'transaction_id' => $transactionId,
                    'coin_amount' => $totalCost,
                    'sender_balance_after' => (int) $sender->wallet_balance,
                    'receiver_gems_after' => (int) $receiver->gems,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('SEND_GIFT_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/gifts/send-batch – send a room gift to multiple recipients in one atomic transaction.
     * Body: gift_id, receiver_ids (int[]), quantity (int), room_id (string).
     */
    public function sendBatch(Request $request)
    {
        try {
            $giftIdRaw = $request->input('gift_id');
            $request->merge([
                'gift_id' => is_numeric($giftIdRaw) ? (int) $giftIdRaw : $giftIdRaw,
            ]);

            $validated = $request->validate([
                'gift_id' => 'required',
                'receiver_ids' => 'required|array|min:1',
                'receiver_ids.*' => 'integer|distinct|exists:users,id',
                'quantity' => 'nullable|integer|min:1|max:100',
                'room_id' => 'required|string',
            ]);

            $roomId = $validated['room_id'];
            $room = Room::find($roomId);

            if (!$room || $room->trashed() || !$room->is_live) {
                return ApiResponse::notFound('Room not found or closed');
            }

            $sender = $request->user();
            $receiverIds = array_values(array_unique($validated['receiver_ids']));

            // Resolve gift (gift_types UUID or virtual_gifts integer fallback)
            $giftType = null;
            $virtualGift = null;
            if (is_numeric($validated['gift_id'])) {
                $virtualGift = VirtualGift::where('is_active', true)->find((int) $validated['gift_id']);
            } else {
                $giftType = GiftType::where('is_active', true)->find($validated['gift_id']);
            }
            if (!$giftType && !$virtualGift) {
                return ApiResponse::notFound('Gift not found or inactive');
            }

            $quantity = $validated['quantity'] ?? 1;
            $giftUnitPrice = $giftType ? (int) $giftType->coin_price : (int) $virtualGift->coin_cost;
            $perReceiverCost = $giftUnitPrice * $quantity;
            $recipientCount = count($receiverIds);
            $totalCost = $perReceiverCost * $recipientCount;

            // Ensure all receivers are in the room
            $activeMembers = \App\Models\RoomMember::where('room_id', $roomId)
                ->whereNull('left_at')
                ->whereIn('user_id', $receiverIds)
                ->pluck('user_id')
                ->all();
            sort($activeMembers);
            $sortedReceiverIds = $receiverIds;
            sort($sortedReceiverIds);
            if ($activeMembers !== $sortedReceiverIds) {
                return ApiResponse::error('RECEIVER_NOT_IN_ROOM', 'One or more receivers are not in this room', 400);
            }

            // Check sender balance for total cost
            $senderBalance = (int) ($sender->wallet_balance ?? 0);
            if ($senderBalance < $totalCost) {
                return ApiResponse::error('INSUFFICIENT_BALANCE', 'Insufficient wallet balance for all recipients', 422);
            }

            // Commission and receiver share per recipient
            $settings = AdminSetting::first();
            $commissionPercent = $settings ? (int) ($settings->gift_commission_percent ?? 0) : 0;
            $adminCommissionPer = (int) floor($perReceiverCost * $commissionPercent / 100);
            $receiverAmountPer = $perReceiverCost - $adminCommissionPer;

            $transactionIds = [];

            DB::beginTransaction();
            try {
                $s = \App\Models\User::where('id', $sender->id)->lockForUpdate()->first();
                if (!$s) {
                    throw new \RuntimeException('Sender not found');
                }
                if ((int) ($s->wallet_balance ?? 0) < $totalCost) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $receivers = \App\Models\User::whereIn('id', $receiverIds)->lockForUpdate()->get()->keyBy('id');
                if ($receivers->count() !== $recipientCount) {
                    throw new \RuntimeException('Receiver not found');
                }

                $s->decrement('wallet_balance', $totalCost);

                foreach ($receiverIds as $rid) {
                    $r = $receivers->get($rid);
                    if (!$r) {
                        throw new \RuntimeException('Receiver not found');
                    }
                    $r->increment('gems', $receiverAmountPer);
                    $r->increment('total_earned_coins', $receiverAmountPer);

                    if ($giftType) {
                        $transaction = Transaction::create([
                            'sender_id' => $sender->id,
                            'receiver_id' => $r->id,
                            'room_id' => $roomId,
                            'gift_type_id' => $giftType->id,
                            'quantity' => $quantity,
                            'coin_amount' => $perReceiverCost,
                        ]);
                        $transactionIds[] = $transaction->id;
                    }
                }

                DB::commit();

                $sender->refresh();

                return ApiResponse::success([
                    'transaction_ids' => $transactionIds,
                    'coin_amount' => $totalCost,
                    'per_receiver_coin_amount' => $perReceiverCost,
                    'receiver_count' => $recipientCount,
                    'sender_balance_after' => (int) $sender->wallet_balance,
                ]);
            } catch (\RuntimeException $e) {
                DB::rollBack();
                if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                    return ApiResponse::error('INSUFFICIENT_BALANCE', 'Insufficient wallet balance for all recipients', 422);
                }
                throw $e;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('SEND_GIFT_BATCH_FAILED', $e->getMessage(), 500);
        }
    }
}


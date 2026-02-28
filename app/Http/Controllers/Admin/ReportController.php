<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function ledger(Request $request): View
    {
        $user = null;
        $walletTransactions = collect();
        $coinTransactions = collect();

        if ($request->filled('user_id') || $request->filled('email')) {
            $query = User::query();
            if ($request->filled('user_id')) {
                $query->where('id', $request->user_id);
            }
            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }
            $user = $query->first();
            if ($user) {
                $walletTransactions = WalletTransaction::where('user_id', $user->id)->orderByDesc('created_at')->limit(100)->get();
                $coinTransactions = CoinTransaction::where('sender_id', $user->id)->orWhere('receiver_id', $user->id)->orderByDesc('created_at')->limit(100)->get();
            }
        }

        return view('admin.reports.ledger', [
            'user' => $user,
            'walletTransactions' => $walletTransactions,
            'coinTransactions' => $coinTransactions,
        ]);
    }

    public function revenue(Request $request): View
    {
        $today = Carbon::today(config('app.timezone'));
        $weekStart = $today->copy()->startOfWeek();

        $revenueToday = (int) CoinTransaction::whereDate('created_at', $today)->sum('admin_commission_coins');
        $revenueWeek = (int) CoinTransaction::where('created_at', '>=', $weekStart)->sum('admin_commission_coins');
        $revenueAll = (int) CoinTransaction::sum('admin_commission_coins');

        $from = $request->filled('from') ? Carbon::parse($request->from, config('app.timezone'))->startOfDay() : $weekStart;
        $to = $request->filled('to') ? Carbon::parse($request->to, config('app.timezone'))->endOfDay() : $today;

        $byDay = CoinTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(admin_commission_coins) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.reports.revenue', [
            'revenueToday' => $revenueToday,
            'revenueWeek' => $revenueWeek,
            'revenueAll' => $revenueAll,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'byDay' => $byDay,
        ]);
    }
}

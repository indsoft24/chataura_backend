<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today(config('app.timezone'));
        $weekStart = $today->copy()->startOfWeek();

        $revenueToday = (int) CoinTransaction::whereDate('created_at', $today)->sum('admin_commission_coins');
        $revenueWeek = (int) CoinTransaction::where('created_at', '>=', $weekStart)->sum('admin_commission_coins');

        return view('admin.dashboard', [
            'revenueToday' => $revenueToday,
            'revenueWeek' => $revenueWeek,
        ]);
    }
}

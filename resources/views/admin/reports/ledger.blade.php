@extends('admin.layout')

@section('title', 'User Ledger')

@section('content')
<h1 class="text-2xl font-semibold mb-6">User Ledger</h1>
<form method="GET" action="{{ route('admin.reports.ledger') }}" class="bg-white rounded-lg shadow p-6 mb-6 max-w-xl">
    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
            <input type="number" name="user_id" id="user_id" value="{{ request('user_id') }}" min="1" placeholder="e.g. 1" class="border border-gray-300 rounded px-3 py-2 w-32">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email (partial)</label>
            <input type="text" name="email" id="email" value="{{ request('email') }}" placeholder="e.g. user@" class="border border-gray-300 rounded px-3 py-2 w-48">
        </div>
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Search</button>
    </div>
</form>

@if (request()->has('user_id') || request()->has('email'))
    @if ($user)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-medium mb-4">User #{{ $user->id }}</h2>
        <p class="text-gray-600">Name: {{ $user->name }}</p>
        <p class="text-gray-600">Email: {{ $user->email ?? '—' }}</p>
        <p class="text-gray-600">Wallet balance: <strong>{{ number_format($user->wallet_balance ?? 0) }} coins</strong></p>
        <p class="text-gray-600">Total earned (coins): <strong>{{ number_format($user->total_earned_coins ?? 0) }}</strong></p>
    </div>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">Wallet transactions (Razorpay)</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount (INR)</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Coins</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($walletTransactions as $wt)
                    <tr>
                        <td class="px-4 py-2">{{ $wt->id }}</td>
                        <td class="px-4 py-2">{{ $wt->status }}</td>
                        <td class="px-4 py-2">{{ number_format($wt->amount_paid_inr ?? 0, 2) }}</td>
                        <td class="px-4 py-2">{{ number_format($wt->coins_credited ?? 0) }}</td>
                        <td class="px-4 py-2">{{ $wt->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                    @endforeach
                    @if ($walletTransactions->isEmpty())
                    <tr><td colspan="5" class="px-4 py-4 text-gray-500">No wallet transactions.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-medium mb-4">Coin transactions (gifts / calls)</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sender</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Receiver</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Gross</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Commission</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Net</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($coinTransactions as $ct)
                    <tr>
                        <td class="px-4 py-2">{{ $ct->transaction_type }}</td>
                        <td class="px-4 py-2">{{ $ct->sender_id }}</td>
                        <td class="px-4 py-2">{{ $ct->receiver_id }}</td>
                        <td class="px-4 py-2">{{ number_format($ct->gross_coins_deducted ?? 0) }}</td>
                        <td class="px-4 py-2">{{ number_format($ct->admin_commission_coins ?? 0) }}</td>
                        <td class="px-4 py-2">{{ number_format($ct->net_coins_received ?? 0) }}</td>
                        <td class="px-4 py-2">{{ $ct->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                    @endforeach
                    @if ($coinTransactions->isEmpty())
                    <tr><td colspan="7" class="px-4 py-4 text-gray-500">No coin transactions.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @else
    <p class="text-gray-600">No user found for the given criteria.</p>
    @endif
@endif

<p class="mt-6"><a href="{{ route('admin.reports.index') }}" class="text-indigo-600 hover:underline">Back to reports</a></p>
@endsection

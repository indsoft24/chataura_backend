@extends('admin.layout')

@section('title', 'Reports')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Reports</h1>
<div class="space-y-4">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-medium mb-2">User ledger</h2>
        <p class="text-gray-600 text-sm mb-4">Search by user ID or email to view wallet balance and transaction history.</p>
        <a href="{{ route('admin.reports.ledger') }}" class="text-indigo-600 hover:underline">Open user ledger</a>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-medium mb-2">Platform revenue</h2>
        <p class="text-gray-600 text-sm mb-4">View platform commission (coins) by day and date range.</p>
        <a href="{{ route('admin.reports.revenue') }}" class="text-indigo-600 hover:underline">Open revenue report</a>
    </div>
</div>
@endsection

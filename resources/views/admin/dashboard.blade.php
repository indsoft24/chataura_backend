@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Dashboard</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-gray-600 text-sm font-medium">Platform revenue (today)</h2>
            <p class="text-2xl font-bold mt-1">{{ number_format($revenueToday) }} coins</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-gray-600 text-sm font-medium">Platform revenue (this week)</h2>
            <p class="text-2xl font-bold mt-1">{{ number_format($revenueWeek) }} coins</p>
        </div>
    </div>
    <p class="text-gray-600">
        <a href="{{ route('admin.reports.revenue') }}" class="text-indigo-600 hover:underline">View revenue report</a> ·
        <a href="{{ route('admin.reports.ledger') }}" class="text-indigo-600 hover:underline">User ledger</a>
    </p>
@endsection

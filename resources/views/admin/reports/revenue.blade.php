@extends('admin.layout')

@section('title', 'Platform Revenue')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Platform Revenue</h1>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-gray-600 text-sm font-medium">Today</h2>
        <p class="text-2xl font-bold mt-1">{{ number_format($revenueToday) }} coins</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-gray-600 text-sm font-medium">This week</h2>
        <p class="text-2xl font-bold mt-1">{{ number_format($revenueWeek) }} coins</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-gray-600 text-sm font-medium">All time</h2>
        <p class="text-2xl font-bold mt-1">{{ number_format($revenueAll) }} coins</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.reports.revenue') }}" class="bg-white rounded-lg shadow p-6 mb-6 max-w-xl">
    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
            <input type="date" name="from" id="from" value="{{ $from }}" class="border border-gray-300 rounded px-3 py-2">
        </div>
        <div>
            <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
            <input type="date" name="to" id="to" value="{{ $to }}" class="border border-gray-300 rounded px-3 py-2">
        </div>
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Apply</button>
    </div>
</form>

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-medium mb-4">Revenue by day ({{ $from }} – {{ $to }})</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Commission (coins)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($byDay as $row)
                <tr>
                    <td class="px-4 py-2">{{ $row->date }}</td>
                    <td class="px-4 py-2">{{ number_format($row->total ?? 0) }}</td>
                </tr>
                @endforeach
                @if ($byDay->isEmpty())
                <tr><td colspan="2" class="px-4 py-4 text-gray-500">No data for this range.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<p class="mt-6"><a href="{{ route('admin.reports.index') }}" class="text-indigo-600 hover:underline">Back to reports</a></p>
@endsection

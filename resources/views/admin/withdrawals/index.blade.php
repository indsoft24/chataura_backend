@extends('admin.layout')

@section('title', 'Withdrawal Requests')

@section('content')
@if (session('success'))
<div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2">{{ session('success') }}</div>
@endif
@if (session('error'))
<div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-2">{{ session('error') }}</div>
@endif
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-semibold">Withdrawal Requests</h1>
    <form method="GET" action="{{ route('admin.withdrawals.index') }}" class="flex gap-2 items-center">
        <label for="status" class="text-sm text-gray-600">Status</label>
        <select name="status" id="status" class="border border-gray-300 rounded px-3 py-1.5 text-sm" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
    </form>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Gems</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Details / Bank</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($requests as $r)
            <tr>
                <td class="px-4 py-2 font-mono">{{ $r->id }}</td>
                <td class="px-4 py-2">{{ $r->user->display_name ?? $r->user->name ?? 'User' }} <span class="text-gray-400">#{{ $r->user_id }}</span></td>
                <td class="px-4 py-2">{{ number_format($r->gems_amount) }}</td>
                <td class="px-4 py-2">
                    {{ $r->payment_method }}
                    @if($r->is_international)
                    <span class="ml-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-800">International</span>
                    @endif
                </td>
                <td class="px-4 py-2 max-w-xs text-sm">
                    <div class="truncate" title="{{ $r->payment_details }}">{{ $r->payment_details }}</div>
                    @if($r->ifsc_code)
                    <div class="text-xs text-gray-500">IFSC: {{ $r->ifsc_code }}</div>
                    @endif
                    @if($r->is_international)
                    <div class="mt-1 text-xs text-gray-600">
                        @if($r->full_name)
                            <div>Account holder: {{ $r->full_name }}</div>
                        @endif
                        @if($r->bank_name)
                            <div>Bank: {{ $r->bank_name }}</div>
                        @endif
                        @if($r->swift_code)
                            <div>SWIFT: {{ $r->swift_code }}</div>
                        @endif
                        @if($r->country)
                            <div>Country: {{ $r->country }}</div>
                        @endif
                    </div>
                    @endif
                </td>
                <td class="px-4 py-2">
                    <span class="px-2 py-0.5 rounded text-xs font-medium
                        @if($r->status === 'pending') bg-yellow-100 text-yellow-800
                        @elseif($r->status === 'approved') bg-green-100 text-green-800
                        @else bg-red-100 text-red-800
                        @endif">{{ ucfirst($r->status) }}</span>
                </td>
                <td class="px-4 py-2 text-sm text-gray-600">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-2 text-right">
                    @if ($r->status === 'pending')
                    <a href="{{ route('admin.withdrawals.approve-form', $r) }}" class="text-green-600 hover:underline">Approve</a>
                    <span class="text-gray-300">|</span>
                    <a href="{{ route('admin.withdrawals.reject-form', $r) }}" class="text-red-600 hover:underline">Reject</a>
                    @else
                    @if ($r->admin_note)
                    <span class="text-xs text-gray-500" title="{{ $r->admin_note }}">Note</span>
                    @else
                    —
                    @endif
                    @endif
                </td>
            </tr>
            @endforeach
            @if ($requests->isEmpty())
            <tr><td colspan="8" class="px-4 py-4 text-gray-500">No withdrawal requests.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@if ($requests->hasPages())
<div class="mt-4">{{ $requests->withQueryString()->links() }}</div>
@endif
@endsection

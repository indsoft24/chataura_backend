@extends('admin.layout')

@section('title', 'Reject Withdrawal')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Reject Withdrawal #{{ $withdrawal->id }}</h1>
<div class="bg-white rounded-lg shadow p-6 max-w-md">
    <p class="text-gray-600 mb-2">
        User <strong>{{ $withdrawal->user->display_name ?? $withdrawal->user->name ?? 'User' }}</strong> — <strong>{{ number_format($withdrawal->gems_amount) }}</strong> gems.
    </p>
    <p class="text-gray-600 mb-4">
        Rejecting will <strong>refund {{ number_format($withdrawal->gems_amount) }} gems</strong> to the user.
        @if($withdrawal->ifsc_code)
            <br><span class="text-sm text-gray-500">IFSC: {{ $withdrawal->ifsc_code }}</span>
        @endif
        @if($withdrawal->is_international)
            <br><span class="text-xs inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-800 mt-1">International</span>
            <br>
            @if($withdrawal->full_name)
                <span class="text-sm text-gray-500">Account holder: {{ $withdrawal->full_name }}</span><br>
            @endif
            @if($withdrawal->bank_name)
                <span class="text-sm text-gray-500">Bank: {{ $withdrawal->bank_name }}</span><br>
            @endif
            @if($withdrawal->swift_code)
                <span class="text-sm text-gray-500">SWIFT: {{ $withdrawal->swift_code }}</span><br>
            @endif
            @if($withdrawal->country)
                <span class="text-sm text-gray-500">Country: {{ $withdrawal->country }}</span>
            @endif
        @endif
    </p>
    <form method="POST" action="{{ route('admin.withdrawals.reject', $withdrawal) }}">
        @csrf
        <div class="mb-4">
            <label for="admin_note" class="block text-sm font-medium text-gray-700 mb-1">Reject reason (required)</label>
            <textarea name="admin_note" id="admin_note" rows="3" required maxlength="500" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="e.g. Invalid UPI ID">{{ old('admin_note') }}</textarea>
            @error('admin_note')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Reject & Refund Gems</button>
            <a href="{{ route('admin.withdrawals.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
        </div>
    </form>
</div>
@endsection

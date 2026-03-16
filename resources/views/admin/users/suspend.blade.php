@extends('admin.layout')

@section('title', 'Suspend User')

@section('content')
<div class="max-w-lg">
    <h1 class="text-2xl font-semibold mb-2">Suspend user</h1>
    <p class="text-gray-600 mb-6">User: <strong>{{ $user->display_name ?? $user->name }}</strong> (ID: {{ $user->id }})</p>
    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="bg-white rounded-lg shadow p-6">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (required)</label>
                <textarea name="reason" id="reason" rows="3" required class="w-full border border-gray-300 rounded px-3 py-2" placeholder="e.g. Violation of community guidelines">{{ old('reason') }}</textarea>
                @error('reason')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="suspended_until" class="block text-sm font-medium text-gray-700 mb-1">Suspend until (optional)</label>
                <input type="datetime-local" name="suspended_until" id="suspended_until" value="{{ old('suspended_until') }}" class="w-full border border-gray-300 rounded px-3 py-2">
                <p class="text-sm text-gray-500 mt-1">Leave empty for permanent suspension.</p>
                @error('suspended_until')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Suspend user</button>
            <a href="{{ route('admin.users.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
        </div>
    </form>
</div>
@endsection

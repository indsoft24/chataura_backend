@extends('admin.layout')

@section('title', 'Users')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Create user / seller</a>
</div>

<form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap gap-2 items-center">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone..." class="border border-gray-300 rounded px-3 py-2 w-64">
    <select name="role" class="border border-gray-300 rounded px-3 py-2">
        <option value="">All roles</option>
        <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>User</option>
        <option value="seller" {{ request('role') === 'seller' ? 'selected' : '' }}>Seller</option>
    </select>
    <button type="submit" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email / Phone</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($users as $u)
            <tr>
                <td class="px-4 py-2">{{ $u->id }}</td>
                <td class="px-4 py-2">{{ $u->display_name ?? $u->name }}</td>
                <td class="px-4 py-2">{{ $u->email ?? $u->phone ?? '—' }}</td>
                <td class="px-4 py-2">
                    <span class="px-2 py-0.5 rounded text-xs {{ $u->role === 'seller' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">{{ $u->role ?? 'user' }}</span>
                </td>
                <td class="px-4 py-2">
                    @if ($u->isSuspended())
                        <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-800" title="{{ $u->suspended_reason }}">Suspended</span>
                        @if ($u->suspended_until)
                            <span class="text-xs text-gray-500 block">until {{ $u->suspended_until->format('M j, Y') }}</span>
                        @else
                            <span class="text-xs text-gray-500 block">indefinite</span>
                        @endif
                    @else
                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">Active</span>
                    @endif
                </td>
                <td class="px-4 py-2">{{ number_format((int)($u->wallet_balance ?? $u->coin_balance ?? 0)) }}</td>
                <td class="px-4 py-2 text-right">
                    <form method="POST" action="{{ route('admin.users.add-credit') }}" class="inline-flex items-center gap-1 mr-2">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                        <input type="number" name="amount" min="1" placeholder="Coins" class="w-20 border border-gray-300 rounded px-2 py-1 text-sm">
                        <button type="submit" class="text-sm text-indigo-600 hover:underline">Add credit</button>
                    </form>
                    @if ($u->id !== auth()->id())
                    @if ($u->isSuspended())
                    <form method="POST" action="{{ route('admin.users.unsuspend', $u) }}" class="inline mr-2" onsubmit="return confirm('Unsuspend this user?');">
                        @csrf
                        <button type="submit" class="text-green-600 hover:underline">Unsuspend</button>
                    </form>
                    @else
                    <a href="{{ route('admin.users.suspend-form', $u) }}" class="text-amber-600 hover:underline mr-2">Suspend</a>
                    @endif
                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                    @else
                    <span class="text-gray-400 text-sm">(you)</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if ($users->isEmpty())
            <tr><td colspan="7" class="px-4 py-4 text-gray-500">No users found.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@if ($users->hasPages())
<div class="mt-4">{{ $users->links() }}</div>
@endif
@endsection

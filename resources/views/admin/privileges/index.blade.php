@extends('admin.layout')

@section('title', 'Wealth Privileges')

@section('content')
@if (session('success'))
<div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2">{{ session('success') }}</div>
@endif
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Wealth Privileges</h1>
    <a href="{{ route('admin.privileges.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Add privilege</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Icon</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Level</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($privileges as $p)
            <tr>
                <td class="px-4 py-2 font-mono">{{ $p->id }}</td>
                <td class="px-4 py-2">{{ $p->title }}</td>
                <td class="px-4 py-2">{{ $p->icon_identifier ?? '—' }}</td>
                <td class="px-4 py-2">{{ $p->level_required }}</td>
                <td class="px-4 py-2">{{ $p->sort_order }}</td>
                <td class="px-4 py-2">{{ $p->is_active ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('admin.privileges.edit', $p) }}" class="text-indigo-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.privileges.destroy', $p) }}" class="inline ml-2" onsubmit="return confirm('Delete this privilege?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if ($privileges->isEmpty())
            <tr><td colspan="7" class="px-4 py-4 text-gray-500">No privileges yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

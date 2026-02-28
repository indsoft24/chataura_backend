@extends('admin.layout')

@section('title', 'Gifts')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Gifts</h1>
    <a href="{{ route('admin.gifts.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Add gift</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Coins</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rarity</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($gifts as $g)
            <tr>
                <td class="px-4 py-2">{{ $g->id }}</td>
                <td class="px-4 py-2">
                    @if ($g->image_url)
                    <img src="{{ $g->image_url }}" alt="" class="h-10 w-10 object-contain rounded">
                    @else
                    —
                    @endif
                </td>
                <td class="px-4 py-2">{{ $g->name }}</td>
                <td class="px-4 py-2">{{ number_format($g->coin_cost) }}</td>
                <td class="px-4 py-2">{{ ucfirst($g->rarity ?? 'common') }}</td>
                <td class="px-4 py-2">{{ $g->is_active ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('admin.gifts.edit', $g) }}" class="text-indigo-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.gifts.destroy', $g) }}" class="inline ml-2" onsubmit="return confirm('Delete this gift?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if ($gifts->isEmpty())
            <tr><td colspan="7" class="px-4 py-4 text-gray-500">No gifts yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

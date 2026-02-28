@extends('admin.layout')

@section('title', 'Packages')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Packages</h1>
    <a href="{{ route('admin.packages.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Add package</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Coins</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Price (INR)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($packages as $pkg)
            <tr>
                <td class="px-4 py-2">{{ $pkg->id }}</td>
                <td class="px-4 py-2">{{ number_format($pkg->coin_amount) }}</td>
                <td class="px-4 py-2">{{ number_format($pkg->price_in_inr, 2) }}</td>
                <td class="px-4 py-2">{{ $pkg->is_active ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('admin.packages.edit', $pkg) }}" class="text-indigo-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.packages.destroy', $pkg) }}" class="inline ml-2" onsubmit="return confirm('Delete this package?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if ($packages->isEmpty())
            <tr><td colspan="5" class="px-4 py-4 text-gray-500">No packages yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

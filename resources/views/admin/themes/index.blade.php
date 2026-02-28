@extends('admin.layout')
@section('title', 'Room Themes')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Room Themes</h1>
    <a href="{{ route('admin.themes.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Add theme</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Media URL</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($themes as $t)
            <tr>
                <td class="px-4 py-2">{{ $t->id }}</td>
                <td class="px-4 py-2">{{ $t->name }}</td>
                <td class="px-4 py-2">{{ $t->type }}</td>
                <td class="px-4 py-2 text-sm truncate max-w-xs" title="{{ $t->media_url }}">{{ $t->media_url }}</td>
                <td class="px-4 py-2">{{ $t->is_active ? 'Yes' : 'No' }}</td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('admin.themes.edit', $t) }}" class="text-indigo-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.themes.destroy', $t) }}" class="inline ml-2" onsubmit="return confirm('Delete this theme?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if ($themes->isEmpty())
            <tr><td colspan="6" class="px-4 py-4 text-gray-500">No themes yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

@extends('admin.layout')

@section('title', 'Countries')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Countries</h1>
    <a href="{{ route('admin.countries.create') }}" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Add country</a>
</div>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Flag</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Emoji</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach ($countries as $c)
            <tr>
                <td class="px-4 py-2 font-mono">{{ $c->id }}</td>
                <td class="px-4 py-2">{{ $c->name }}</td>
                <td class="px-4 py-2">
                    @if ($c->flag_url)
                    <img src="{{ $c->flag_url }}" alt="" class="h-8 w-auto object-contain rounded">
                    @else
                    —
                    @endif
                </td>
                <td class="px-4 py-2">{{ $c->flag_emoji ?? '—' }}</td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ route('admin.countries.edit', $c) }}" class="text-indigo-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.countries.destroy', $c) }}" class="inline ml-2" onsubmit="return confirm('Delete this country?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
            @if ($countries->isEmpty())
            <tr><td colspan="5" class="px-4 py-4 text-gray-500">No countries yet.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

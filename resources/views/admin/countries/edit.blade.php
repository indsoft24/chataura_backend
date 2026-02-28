@extends('admin.layout')

@section('title', 'Edit Country')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Edit Country ({{ $country->id }})</h1>
    <form method="POST" action="{{ route('admin.countries.update', $country) }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 max-w-md">
        @csrf
        @method('PUT')
        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $country->name) }}" required maxlength="255" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="flag_emoji" class="block text-sm font-medium text-gray-700 mb-1">Flag emoji (optional)</label>
                <input type="text" name="flag_emoji" id="flag_emoji" value="{{ old('flag_emoji', $country->flag_emoji) }}" maxlength="10" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('flag_emoji')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            @if ($country->flag_url)
                <div>
                    <span class="text-sm text-gray-600">Current flag:</span>
                    <img src="{{ $country->flag_url }}" alt="" class="h-12 w-auto object-contain rounded mt-1">
                </div>
            @endif
            <div>
                <label for="flag" class="block text-sm font-medium text-gray-700 mb-1">New flag icon (PNG/SVG, optional) – replaces current</label>
                <input type="file" name="flag" id="flag" accept=".png,.jpg,.jpeg,.svg" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('flag')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-6 flex gap-2">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Update</button>
            <a href="{{ route('admin.countries.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
        </div>
    </form>
@endsection

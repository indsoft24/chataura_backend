@extends('admin.layout')

@section('title', 'Edit Wealth Privilege')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Wealth Privilege</h1>
<form method="POST" action="{{ route('admin.privileges.update', $privilege) }}" class="bg-white rounded-lg shadow p-6 max-w-md">
    @csrf
    @method('PUT')
    <div class="space-y-4">
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title', $privilege->title) }}" required maxlength="255" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('title')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" id="description" rows="2" maxlength="500" class="w-full border border-gray-300 rounded px-3 py-2">{{ old('description', $privilege->description) }}</textarea>
            @error('description')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="icon_identifier" class="block text-sm font-medium text-gray-700 mb-1">Icon identifier</label>
            <input type="text" name="icon_identifier" id="icon_identifier" value="{{ old('icon_identifier', $privilege->icon_identifier) }}" required maxlength="50" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('icon_identifier')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="level_required" class="block text-sm font-medium text-gray-700 mb-1">Level required</label>
            <input type="number" name="level_required" id="level_required" min="0" max="255" value="{{ old('level_required', $privilege->level_required) }}" required class="w-full border border-gray-300 rounded px-3 py-2">
            @error('level_required')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" min="0" value="{{ old('sort_order', $privilege->sort_order) }}" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('sort_order')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $privilege->is_active) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">Active</span>
            </label>
        </div>
    </div>
    <div class="mt-6 flex gap-2">
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Update</button>
        <a href="{{ route('admin.privileges.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection

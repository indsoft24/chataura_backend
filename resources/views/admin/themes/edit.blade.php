@extends('admin.layout')
@section('title', 'Edit Room Theme')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Room Theme</h1>
<form method="POST" action="{{ route('admin.themes.update', $theme) }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 max-w-md">
    @csrf
    @method('PUT')
    <div class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $theme->name) }}" required maxlength="100" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" id="type" required class="w-full border border-gray-300 rounded px-3 py-2">
                <option value="lottie_animation" {{ old('type', $theme->type) === 'lottie_animation' ? 'selected' : '' }}>Lottie animation</option>
                <option value="static_image" {{ old('type', $theme->type) === 'static_image' ? 'selected' : '' }}>Static image</option>
            </select>
            @error('type')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        @if ($theme->media_url)
        <div>
            <span class="text-sm text-gray-600">Current media:</span>
            @if ($theme->type === 'static_image')
                <img src="{{ $theme->media_url }}" alt="" class="mt-1 h-20 w-20 object-contain rounded border">
            @else
                <a href="{{ $theme->media_url }}" target="_blank" rel="noopener" class="text-indigo-600 text-sm mt-1 block truncate max-w-md">View file</a>
            @endif
        </div>
        @endif
        <div>
            <label for="media_file" class="block text-sm font-medium text-gray-700 mb-1">Upload file (.json, .png, .jpg, .gif, .webp)</label>
            <input type="file" name="media_file" id="media_file" accept=".json,image/png,image/jpeg,image/gif,image/webp" class="w-full border border-gray-300 rounded px-3 py-2">
            <p class="text-xs text-gray-500 mt-1">Max 5MB. Leave empty to keep current. If both provided, file takes priority.</p>
            @error('media_file')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="media_url" class="block text-sm font-medium text-gray-700 mb-1">Fallback URL</label>
            <input type="url" name="media_url" id="media_url" value="{{ old('media_url', $theme->media_url) }}" maxlength="500" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('media_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $theme->is_active) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">Active</span>
            </label>
        </div>
    </div>
    <div class="mt-6 flex gap-2">
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Update</button>
        <a href="{{ route('admin.themes.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection

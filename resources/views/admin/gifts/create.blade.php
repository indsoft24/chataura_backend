@extends('admin.layout')

@section('title', 'Add Gift')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Add Gift</h1>
    <form method="POST" action="{{ route('admin.gifts.store') }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 max-w-md">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="coin_cost" class="block text-sm font-medium text-gray-700 mb-1">Coin cost</label>
                <input type="number" name="coin_cost" id="coin_cost" min="1" value="{{ old('coin_cost') }}" required class="w-full border border-gray-300 rounded px-3 py-2">
                @error('coin_cost')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="rarity" class="block text-sm font-medium text-gray-700 mb-1">Rarity</label>
                <select name="rarity" id="rarity" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="common" {{ old('rarity', 'common') === 'common' ? 'selected' : '' }}>Common</option>
                    <option value="rare" {{ old('rarity') === 'rare' ? 'selected' : '' }}>Rare</option>
                    <option value="epic" {{ old('rarity') === 'epic' ? 'selected' : '' }}>Epic</option>
                    <option value="legendary" {{ old('rarity') === 'legendary' ? 'selected' : '' }}>Legendary</option>
                </select>
                @error('rarity')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="image" class="block text-sm font-medium text-gray-700 mb-1">Icon upload (PNG/GIF/SVG, max 2MB)</label>
                <input type="file" name="image" id="image" accept=".png,.gif,.svg" class="w-full border border-gray-300 rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Stored under gifts/.</p>
                @error('image')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="animation_file" class="block text-sm font-medium text-gray-700 mb-1">Upload animation (.json)</label>
                <input type="file" name="animation_file" id="animation_file" accept=".json,image/png,image/jpeg,image/gif,image/webp" class="w-full border border-gray-300 rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Lottie .json or image. Max 5MB. Stored under gifts/animations/.</p>
                @error('animation_file')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="animation_url" class="block text-sm font-medium text-gray-700 mb-1">Fallback URL (animation)</label>
                <input type="url" name="animation_url" id="animation_url" value="{{ old('animation_url') }}" maxlength="500" placeholder="https://your-domain.com/storage/gifts/animations/car_driving.json" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('animation_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700">Active</span>
                </label>
            </div>
        </div>
        <div class="mt-6 flex gap-2">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Create</button>
            <a href="{{ route('admin.gifts.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
        </div>
    </form>
@endsection

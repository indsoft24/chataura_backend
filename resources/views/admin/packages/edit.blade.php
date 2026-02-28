@extends('admin.layout')

@section('title', 'Edit Package')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Edit Package</h1>
<form method="POST" action="{{ route('admin.packages.update', $package) }}" class="bg-white rounded-lg shadow p-6 max-w-md">
    @csrf
    @method('PUT')
    <div class="space-y-4">
        <div>
            <label for="coin_amount" class="block text-sm font-medium text-gray-700 mb-1">Coins</label>
            <input type="number" name="coin_amount" id="coin_amount" min="1" value="{{ old('coin_amount', $package->coin_amount) }}" required class="w-full border border-gray-300 rounded px-3 py-2">
            @error('coin_amount')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="price_in_inr" class="block text-sm font-medium text-gray-700 mb-1">Price (INR)</label>
            <input type="number" name="price_in_inr" id="price_in_inr" min="0" step="0.01" value="{{ old('price_in_inr', $package->price_in_inr) }}" required class="w-full border border-gray-300 rounded px-3 py-2">
            @error('price_in_inr')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700">Active</span>
            </label>
        </div>
    </div>
    <div class="mt-6 flex gap-2">
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Update</button>
        <a href="{{ route('admin.packages.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection

@extends('admin.layout')

@section('title', 'Create User / Seller')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Create user or seller account</h1>
<form method="POST" action="{{ route('admin.users.store') }}" class="bg-white rounded-lg shadow p-6 max-w-md">
    @csrf
    <div class="space-y-4">
        <div>
            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
            <select name="role" id="role" required class="w-full border border-gray-300 rounded px-3 py-2">
                <option value="user" {{ old('role', 'user') === 'user' ? 'selected' : '' }}>User</option>
                <option value="seller" {{ old('role') === 'seller' ? 'selected' : '' }}>Seller</option>
            </select>
            @error('role')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded px-3 py-2">
            @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email (optional)</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('email')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone (optional)</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="w-full border border-gray-300 rounded px-3 py-2">
            @error('phone')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <p class="text-sm text-gray-500">At least one of email or phone is required.</p>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required class="w-full border border-gray-300 rounded px-3 py-2">
            @error('password')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required class="w-full border border-gray-300 rounded px-3 py-2">
        </div>
    </div>
    <div class="mt-6 flex gap-2">
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Create account</button>
        <a href="{{ route('admin.users.index') }}" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
    </div>
</form>
@endsection

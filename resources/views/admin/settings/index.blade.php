@extends('admin.layout')

@section('title', 'Settings')

@section('content')
    <h1 class="text-2xl font-semibold mb-6">Settings</h1>
    <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white rounded-lg shadow p-6 max-w-xl">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label for="gift_commission_percent" class="block text-sm font-medium text-gray-700 mb-1">Gift commission (%)</label>
                <input type="number" name="gift_commission_percent" id="gift_commission_percent" min="0" max="100" value="{{ old('gift_commission_percent', $settings->gift_commission_percent) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('gift_commission_percent')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audio_call_price_per_min" class="block text-sm font-medium text-gray-700 mb-1">Audio call price (coins/min)</label>
                <input type="number" name="audio_call_price_per_min" id="audio_call_price_per_min" min="0" value="{{ old('audio_call_price_per_min', $settings->audio_call_price_per_min) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('audio_call_price_per_min')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audio_call_commission_percent" class="block text-sm font-medium text-gray-700 mb-1">Audio call commission (%)</label>
                <input type="number" name="audio_call_commission_percent" id="audio_call_commission_percent" min="0" max="100" value="{{ old('audio_call_commission_percent', $settings->audio_call_commission_percent) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('audio_call_commission_percent')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="video_call_price_per_min" class="block text-sm font-medium text-gray-700 mb-1">Video call price (coins/min)</label>
                <input type="number" name="video_call_price_per_min" id="video_call_price_per_min" min="0" value="{{ old('video_call_price_per_min', $settings->video_call_price_per_min) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('video_call_price_per_min')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="video_call_commission_percent" class="block text-sm font-medium text-gray-700 mb-1">Video call commission (%)</label>
                <input type="number" name="video_call_commission_percent" id="video_call_commission_percent" min="0" max="100" value="{{ old('video_call_commission_percent', $settings->video_call_commission_percent) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('video_call_commission_percent')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <hr class="my-4 border-gray-200">
            <h2 class="text-lg font-medium text-gray-800 mb-2">Refer & Earn</h2>
            <div>
                <label for="referral_reward_referrer" class="block text-sm font-medium text-gray-700 mb-1">Referrer reward (referral coins)</label>
                <input type="number" name="referral_reward_referrer" id="referral_reward_referrer" min="0" value="{{ old('referral_reward_referrer', $settings->referral_reward_referrer ?? 100) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('referral_reward_referrer')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="referral_reward_referee" class="block text-sm font-medium text-gray-700 mb-1">Referee reward (referral coins)</label>
                <input type="number" name="referral_reward_referee" id="referral_reward_referee" min="0" value="{{ old('referral_reward_referee', $settings->referral_reward_referee ?? 50) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('referral_reward_referee')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="referral_coin_conversion_rate" class="block text-sm font-medium text-gray-700 mb-1">Conversion rate (gold coins per 1 referral coin)</label>
                <input type="number" name="referral_coin_conversion_rate" id="referral_coin_conversion_rate" min="1" value="{{ old('referral_coin_conversion_rate', $settings->referral_coin_conversion_rate ?? 1) }}" class="w-full border border-gray-300 rounded px-3 py-2">
                @error('referral_coin_conversion_rate')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-6">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700">Save</button>
        </div>
    </form>
@endsection

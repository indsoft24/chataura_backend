<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = AdminSetting::get();
        return view('admin.settings.index', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gift_commission_percent' => 'required|integer|min:0|max:100',
            'audio_call_price_per_min' => 'required|integer|min:0',
            'audio_call_commission_percent' => 'required|integer|min:0|max:100',
            'video_call_price_per_min' => 'required|integer|min:0',
            'video_call_commission_percent' => 'required|integer|min:0|max:100',
            'referral_reward_referrer' => 'required|integer|min:0',
            'referral_reward_referee' => 'required|integer|min:0',
            'referral_coin_conversion_rate' => 'required|integer|min:1',
        ]);

        $settings = AdminSetting::get();
        $settings->update($validated);

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated.');
    }
}

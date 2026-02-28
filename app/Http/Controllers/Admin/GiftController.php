<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualGift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GiftController extends Controller
{
    private const ANIMATIONS_DISK_PATH = 'gifts/animations';

    public function index(): View
    {
        $gifts = VirtualGift::orderBy('coin_cost')->get();
        return view('admin.gifts.index', ['gifts' => $gifts]);
    }

    public function create(): View
    {
        return view('admin.gifts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'coin_cost' => 'required|integer|min:1',
            'rarity' => 'nullable|string|in:common,rare,epic,legendary',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:png,gif,svg|max:2048',
            'animation_file' => 'nullable|file|mimes:json,png,jpg,jpeg,gif,webp|max:5120',
            'animation_url' => 'nullable|string|max:500',
        ], [
            'animation_file.mimes' => 'Upload a Lottie .json file or an image (PNG, JPG, GIF, WebP).',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['rarity'] = $validated['rarity'] ?? 'common';

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('gifts', 'public');
            $validated['image_url'] = url('/storage/' . $path);
        } else {
            $validated['image_url'] = $request->input('image_url') ?: null;
        }

        if ($request->hasFile('animation_file')) {
            $file = $request->file('animation_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::ANIMATIONS_DISK_PATH, $filename, 'public');
            $validated['animation_url'] = url('/storage/' . $path);
        } else {
            $validated['animation_url'] = $request->input('animation_url') ?: null;
        }

        VirtualGift::create($validated);
        return redirect()->route('admin.gifts.index')->with('success', 'Gift created.');
    }

    public function edit(VirtualGift $gift): View
    {
        return view('admin.gifts.edit', ['gift' => $gift]);
    }

    public function update(Request $request, VirtualGift $gift): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'coin_cost' => 'required|integer|min:1',
            'rarity' => 'nullable|string|in:common,rare,epic,legendary',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:png,gif,svg|max:2048',
            'animation_file' => 'nullable|file|mimes:json,png,jpg,jpeg,gif,webp|max:5120',
            'animation_url' => 'nullable|string|max:500',
        ], [
            'animation_file.mimes' => 'Upload a Lottie .json file or an image (PNG, JPG, GIF, WebP).',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['rarity'] = $validated['rarity'] ?? 'common';

        if ($request->hasFile('image')) {
            if ($gift->image_url && str_contains($gift->image_url, '/storage/gifts/')) {
                $oldFile = public_path('storage/gifts/' . basename(parse_url($gift->image_url, PHP_URL_PATH)));
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $path = $request->file('image')->store('gifts', 'public');
            $validated['image_url'] = url('/storage/' . $path);
        } elseif ($request->filled('image_url')) {
            $validated['image_url'] = $request->input('image_url');
        }

        if ($request->hasFile('animation_file')) {
            if ($gift->animation_url && str_contains($gift->animation_url, '/storage/gifts/animations/')) {
                $oldFile = public_path('storage/gifts/animations/' . basename(parse_url($gift->animation_url, PHP_URL_PATH)));
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $file = $request->file('animation_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::ANIMATIONS_DISK_PATH, $filename, 'public');
            $validated['animation_url'] = url('/storage/' . $path);
        } elseif ($request->filled('animation_url')) {
            $validated['animation_url'] = $request->input('animation_url');
        } else {
            $validated['animation_url'] = $gift->animation_url;
        }

        $gift->update($validated);
        return redirect()->route('admin.gifts.index')->with('success', 'Gift updated.');
    }

    public function destroy(VirtualGift $gift): RedirectResponse
    {
        if ($gift->image_url && str_contains($gift->image_url, '/storage/gifts/')) {
            $oldFile = public_path('storage/gifts/' . basename(parse_url($gift->image_url, PHP_URL_PATH)));
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
        if ($gift->animation_url && str_contains($gift->animation_url, '/storage/gifts/animations/')) {
            $oldFile = public_path('storage/gifts/animations/' . basename(parse_url($gift->animation_url, PHP_URL_PATH)));
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
        $gift->delete();
        return redirect()->route('admin.gifts.index')->with('success', 'Gift deleted.');
    }
}

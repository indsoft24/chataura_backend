<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualGift;
use App\Services\ApiCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function store(Request $request, ApiCacheService $cache): RedirectResponse
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

        $pullZone = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('gifts', 'bunnycdn');
            $validated['image_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $request->input('image_url');
        } else {
            $validated['image_url'] = $request->input('image_url') ?: null;
        }

        if ($request->hasFile('animation_file')) {
            $file = $request->file('animation_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::ANIMATIONS_DISK_PATH, $filename, 'bunnycdn');
            $validated['animation_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $request->input('animation_url');
        } else {
            $validated['animation_url'] = $request->input('animation_url') ?: null;
        }

        VirtualGift::create($validated);
        $cache->bumpVersion('gifts');
        $cache->bumpVersion('gift_types');
        return redirect()->route('admin.gifts.index')->with('success', 'Gift created.');
    }

    public function edit(VirtualGift $gift): View
    {
        return view('admin.gifts.edit', ['gift' => $gift]);
    }

    public function update(Request $request, VirtualGift $gift, ApiCacheService $cache): RedirectResponse
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

        $pullZone = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
        if ($request->hasFile('image')) {
            $this->deleteGiftFileIfOurs($gift->image_url);
            $path = $request->file('image')->store('gifts', 'bunnycdn');
            $validated['image_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $request->input('image_url');
        } elseif ($request->filled('image_url')) {
            $validated['image_url'] = $request->input('image_url');
        }

        if ($request->hasFile('animation_file')) {
            $this->deleteGiftFileIfOurs($gift->animation_url);
            $file = $request->file('animation_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::ANIMATIONS_DISK_PATH, $filename, 'bunnycdn');
            $validated['animation_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $gift->animation_url;
        } elseif ($request->filled('animation_url')) {
            $validated['animation_url'] = $request->input('animation_url');
        } else {
            $validated['animation_url'] = $gift->animation_url;
        }

        $gift->update($validated);
        $cache->bumpVersion('gifts');
        $cache->bumpVersion('gift_types');
        return redirect()->route('admin.gifts.index')->with('success', 'Gift updated.');
    }

    public function destroy(VirtualGift $gift, ApiCacheService $cache): RedirectResponse
    {
        $this->deleteGiftFileIfOurs($gift->image_url);
        $this->deleteGiftFileIfOurs($gift->animation_url);
        $gift->delete();
        $cache->bumpVersion('gifts');
        $cache->bumpVersion('gift_types');
        return redirect()->route('admin.gifts.index')->with('success', 'Gift deleted.');
    }

    private function deleteGiftFileIfOurs(?string $fileUrl): void
    {
        if ($fileUrl === null || $fileUrl === '') {
            return;
        }
        $cdnUrl = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
        if ($cdnUrl !== '' && str_starts_with($fileUrl, $cdnUrl . '/')) {
            $path = substr($fileUrl, strlen($cdnUrl . '/'));
            if ($path !== '' && Storage::disk('bunnycdn')->exists($path)) {
                Storage::disk('bunnycdn')->delete($path);
            }
            return;
        }
        if (str_contains($fileUrl, '/storage/')) {
            $path = parse_url($fileUrl, PHP_URL_PATH);
            if ($path) {
                $relative = ltrim(preg_replace('#^/storage/#', '', $path), '/');
                if ($relative !== '' && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                }
            }
        }
    }
}

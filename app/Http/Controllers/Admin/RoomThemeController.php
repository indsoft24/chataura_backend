<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomTheme;
use App\Services\ApiCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RoomThemeController extends Controller
{
    private const THEMES_DISK_PATH = 'themes';

    public function index(): View
    {
        $themes = RoomTheme::orderBy('name')->get();
        return view('admin.themes.index', ['themes' => $themes]);
    }

    public function create(): View
    {
        return view('admin.themes.create');
    }

    public function store(Request $request, ApiCacheService $cache): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:static_image,lottie_animation',
            'media_url' => 'nullable|string|max:500',
            'media_file' => 'nullable|file|mimes:json,png,jpg,jpeg,gif,webp|max:5120',
            'is_active' => 'boolean',
        ], [
            'media_file.mimes' => 'Upload a Lottie .json file or an image (PNG, JPG, GIF, WebP).',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $mediaUrl = $this->resolveMediaUrl($request, null);
        if (!$mediaUrl) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['media_url' => 'Either upload a file (JSON or image) or enter a media URL.']);
        }
        $validated['media_url'] = $mediaUrl;

        RoomTheme::create($validated);
        $cache->bumpVersion('room_themes');
        return redirect()->route('admin.themes.index')->with('success', 'Theme created.');
    }

    public function edit(RoomTheme $theme): View
    {
        return view('admin.themes.edit', ['theme' => $theme]);
    }

    public function update(Request $request, RoomTheme $theme, ApiCacheService $cache): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:static_image,lottie_animation',
            'media_url' => 'nullable|string|max:500',
            'media_file' => 'nullable|file|mimes:json,png,jpg,jpeg,gif,webp|max:5120',
            'is_active' => 'boolean',
        ], [
            'media_file.mimes' => 'Upload a Lottie .json file or an image (PNG, JPG, GIF, WebP).',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $mediaUrl = $this->resolveMediaUrl($request, $theme);
        if ($mediaUrl !== null) {
            $validated['media_url'] = $mediaUrl;
        }

        $theme->update($validated);
        $cache->bumpVersion('room_themes');
        return redirect()->route('admin.themes.index')->with('success', 'Theme updated.');
    }

    /**
     * Get media URL from uploaded file (saved to BunnyCDN) or from request media_url.
     * On update, if no new file and no media_url sent, returns null (keep existing).
     */
    private function resolveMediaUrl(Request $request, ?RoomTheme $theme): ?string
    {
        if ($request->hasFile('media_file')) {
            $this->deleteThemeMediaIfOurs($theme?->media_url);
            $file = $request->file('media_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::THEMES_DISK_PATH, $filename, 'bunnycdn');
            $pullZone = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
            return ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $theme?->media_url;
        }
        if ($request->filled('media_url')) {
            return $request->input('media_url');
        }
        return $theme?->media_url;
    }

    private function deleteThemeMediaIfOurs(?string $mediaUrl): void
    {
        if ($mediaUrl === null || $mediaUrl === '') {
            return;
        }
        $cdnUrl = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
        if ($cdnUrl !== '' && str_starts_with($mediaUrl, $cdnUrl . '/')) {
            $path = substr($mediaUrl, strlen($cdnUrl . '/'));
            if ($path !== '' && Storage::disk('bunnycdn')->exists($path)) {
                Storage::disk('bunnycdn')->delete($path);
            }
            return;
        }
        if ($mediaUrl && str_contains($mediaUrl, '/storage/themes/')) {
            $path = parse_url($mediaUrl, PHP_URL_PATH);
            if ($path) {
                $relative = ltrim(preg_replace('#^/storage/#', '', $path), '/');
                if ($relative !== '' && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                }
            }
        }
    }

    public function destroy(RoomTheme $theme, ApiCacheService $cache): RedirectResponse
    {
        $this->deleteThemeMediaIfOurs($theme->media_url);
        $theme->delete();
        $cache->bumpVersion('room_themes');
        return redirect()->route('admin.themes.index')->with('success', 'Theme deleted.');
    }
}

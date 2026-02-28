<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomTheme;
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

    public function store(Request $request): RedirectResponse
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
        return redirect()->route('admin.themes.index')->with('success', 'Theme created.');
    }

    public function edit(RoomTheme $theme): View
    {
        return view('admin.themes.edit', ['theme' => $theme]);
    }

    public function update(Request $request, RoomTheme $theme): RedirectResponse
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
        return redirect()->route('admin.themes.index')->with('success', 'Theme updated.');
    }

    /**
     * Get media URL from uploaded file (saved to storage) or from request media_url.
     * On update, if no new file and no media_url sent, returns null (keep existing).
     */
    private function resolveMediaUrl(Request $request, ?RoomTheme $theme): ?string
    {
        if ($request->hasFile('media_file')) {
            if ($theme && $theme->media_url && str_contains($theme->media_url, '/storage/themes/')) {
                $oldFile = public_path('storage/themes/' . basename(parse_url($theme->media_url, PHP_URL_PATH)));
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $file = $request->file('media_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs(self::THEMES_DISK_PATH, $filename, 'public');
            return url('/storage/' . $path);
        }
        if ($request->filled('media_url')) {
            return $request->input('media_url');
        }
        return $theme?->media_url;
    }

    public function destroy(RoomTheme $theme): RedirectResponse
    {
        if ($theme->media_url && str_contains($theme->media_url, '/storage/themes/')) {
            $oldFile = public_path('storage/themes/' . basename(parse_url($theme->media_url, PHP_URL_PATH)));
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
        $theme->delete();
        return redirect()->route('admin.themes.index')->with('success', 'Theme deleted.');
    }
}

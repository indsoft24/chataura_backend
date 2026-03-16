<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ApiCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(): View
    {
        $countries = Country::orderBy('name')->get();
        return view('admin.countries.index', ['countries' => $countries]);
    }

    public function create(): View
    {
        return view('admin.countries.create');
    }

    public function store(Request $request, ApiCacheService $cache): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'required|string|max:10|unique:countries,id',
            'name' => 'required|string|max:255',
            'flag_emoji' => 'nullable|string|max:10',
            'flag' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:512',
        ]);

        $validated['flag_url'] = null;
        if ($request->hasFile('flag')) {
            $path = $request->file('flag')->store('flags', 'bunnycdn');
            $pullZone = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
            $validated['flag_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : null;
        }

        Country::create([
            'id' => $validated['id'],
            'name' => $validated['name'],
            'flag_emoji' => $validated['flag_emoji'] ?? null,
            'flag_url' => $validated['flag_url'],
        ]);
        $cache->bumpVersion('countries');

        return redirect()->route('admin.countries.index')->with('success', 'Country created.');
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', ['country' => $country]);
    }

    public function update(Request $request, Country $country, ApiCacheService $cache): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'flag_emoji' => 'nullable|string|max:10',
            'flag' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:512',
        ]);

        $update = [
            'name' => $validated['name'],
            'flag_emoji' => $validated['flag_emoji'] ?? null,
        ];
        if ($request->hasFile('flag')) {
            $this->deleteCountryFlagIfOurs($country->flag_url);
            $path = $request->file('flag')->store('flags', 'bunnycdn');
            $pullZone = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
            $update['flag_url'] = ($path !== false && $path !== '' && $pullZone !== '') ? $pullZone . '/' . ltrim($path, '/') : $country->flag_url;
        }
        $country->update($update);
        $cache->bumpVersion('countries');

        return redirect()->route('admin.countries.index')->with('success', 'Country updated.');
    }

    public function destroy(Country $country, ApiCacheService $cache): RedirectResponse
    {
        $this->deleteCountryFlagIfOurs($country->flag_url);
        $country->delete();
        $cache->bumpVersion('countries');
        return redirect()->route('admin.countries.index')->with('success', 'Country deleted.');
    }

    private function deleteCountryFlagIfOurs(?string $flagUrl): void
    {
        if ($flagUrl === null || $flagUrl === '') {
            return;
        }
        $cdnUrl = rtrim(config('filesystems.disks.bunnycdn.pull_zone') ?: config('bunny.cdn_url', ''), '/');
        if ($cdnUrl !== '' && str_starts_with($flagUrl, $cdnUrl . '/')) {
            $path = substr($flagUrl, strlen($cdnUrl . '/'));
            if ($path !== '' && Storage::disk('bunnycdn')->exists($path)) {
                Storage::disk('bunnycdn')->delete($path);
            }
            return;
        }
        $base = rtrim(config('app.url'), '/') . '/storage/';
        if (str_starts_with($flagUrl, $base)) {
            $path = substr($flagUrl, strlen($base));
            if ($path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}

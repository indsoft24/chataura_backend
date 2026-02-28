<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'required|string|max:10|unique:countries,id',
            'name' => 'required|string|max:255',
            'flag_emoji' => 'nullable|string|max:10',
            'flag' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:512',
        ]);

        $validated['flag_url'] = null;
        if ($request->hasFile('flag')) {
            $path = $request->file('flag')->store('flags', 'public');
            $validated['flag_url'] = rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
        }

        Country::create([
            'id' => $validated['id'],
            'name' => $validated['name'],
            'flag_emoji' => $validated['flag_emoji'] ?? null,
            'flag_url' => $validated['flag_url'],
        ]);

        return redirect()->route('admin.countries.index')->with('success', 'Country created.');
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', ['country' => $country]);
    }

    public function update(Request $request, Country $country): RedirectResponse
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
            if ($country->flag_url) {
                $oldPath = str_replace(rtrim(config('app.url'), '/') . '/storage/', '', $country->flag_url);
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $path = $request->file('flag')->store('flags', 'public');
            $update['flag_url'] = rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
        }
        $country->update($update);

        return redirect()->route('admin.countries.index')->with('success', 'Country updated.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        if ($country->flag_url) {
            $oldPath = str_replace(rtrim(config('app.url'), '/') . '/storage/', '', $country->flag_url);
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        $country->delete();
        return redirect()->route('admin.countries.index')->with('success', 'Country deleted.');
    }
}

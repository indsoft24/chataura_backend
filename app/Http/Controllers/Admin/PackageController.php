<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(): View
    {
        $packages = WalletPackage::orderBy('coin_amount')->get();
        return view('admin.packages.index', ['packages' => $packages]);
    }

    public function create(): View
    {
        return view('admin.packages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'coin_amount' => 'required|integer|min:1',
            'price_in_inr' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        WalletPackage::create($validated);
        return redirect()->route('admin.packages.index')->with('success', 'Package created.');
    }

    public function edit(WalletPackage $package): View
    {
        return view('admin.packages.edit', ['package' => $package]);
    }

    public function update(Request $request, WalletPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'coin_amount' => 'required|integer|min:1',
            'price_in_inr' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $package->update($validated);
        return redirect()->route('admin.packages.index')->with('success', 'Package updated.');
    }

    public function destroy(WalletPackage $package): RedirectResponse
    {
        $package->delete();
        return redirect()->route('admin.packages.index')->with('success', 'Package deleted.');
    }
}

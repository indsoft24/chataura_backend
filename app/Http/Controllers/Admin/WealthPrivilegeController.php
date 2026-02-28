<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WealthPrivilege;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WealthPrivilegeController extends Controller
{
    public function index(): View
    {
        $privileges = WealthPrivilege::orderBy('sort_order')->orderBy('id')->get();
        return view('admin.privileges.index', ['privileges' => $privileges]);
    }

    public function create(): View
    {
        return view('admin.privileges.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icon_identifier' => 'required|string|max:50',
            'level_required' => 'required|integer|min:0|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        WealthPrivilege::create($validated);
        return redirect()->route('admin.privileges.index')->with('success', 'Privilege created.');
    }

    public function edit(WealthPrivilege $privilege): View
    {
        return view('admin.privileges.edit', ['privilege' => $privilege]);
    }

    public function update(Request $request, WealthPrivilege $privilege): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'icon_identifier' => 'required|string|max:50',
            'level_required' => 'required|integer|min:0|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $privilege->update($validated);
        return redirect()->route('admin.privileges.index')->with('success', 'Privilege updated.');
    }

    public function destroy(WealthPrivilege $privilege): RedirectResponse
    {
        $privilege->delete();
        return redirect()->route('admin.privileges.index')->with('success', 'Privilege deleted.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->orderBy('id', 'desc');
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%");
            });
        }
        $users = $query->paginate(20)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_SELLER])],
        ]);
        if (empty($validated['email']) && empty($validated['phone'])) {
            return back()->withInput()->withErrors(['email' => 'Provide at least email or phone.']);
        }
        $validated['password'] = Hash::make($validated['password']);
        $validated['display_name'] = $validated['name'];
        $validated['wallet_balance'] = 0;
        $validated['coin_balance'] = 0;
        User::create($validated);
        return redirect()->route('admin.users.index')->with('success', 'User account created.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === (int) auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function addCredit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|integer|min:1',
        ]);
        $user = User::findOrFail($validated['user_id']);
        $user->wallet_balance = (int) $user->wallet_balance + (int) $validated['amount'];
        $user->coin_balance = (int) ($user->coin_balance ?? 0) + (int) $validated['amount'];
        $user->save();
        return redirect()->route('admin.users.index')->with('success', 'Credit added to user.');
    }
}

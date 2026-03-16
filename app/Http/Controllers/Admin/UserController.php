<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiCacheService;
use App\Services\JwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    /**
     * Suspend a user. Invalidates all refresh tokens so they cannot obtain new access tokens.
     */
    public function suspend(Request $request, User $user, JwtService $jwtService, ApiCacheService $cache): RedirectResponse
    {
        if ($user->id === (int) auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot suspend your own account.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:2000',
            'suspended_until' => 'nullable|date|after:now',
        ]);

        $user->is_suspended = true;
        $user->suspended_reason = $validated['reason'];
        $user->suspended_until = isset($validated['suspended_until'])
            ? \Carbon\Carbon::parse($validated['suspended_until'])
            : null;
        $user->save();

        $jwtService->revokeAllRefreshTokensForUser($user);

        $this->invalidateUserCaches($cache);
        $this->auditSuspension('suspend', $user, $validated['reason'], $user->suspended_until);

        return redirect()->route('admin.users.index')->with('success', 'User suspended. All sessions have been invalidated.');
    }

    /**
     * Unsuspend a user.
     */
    /**
     * Show form to suspend a user (reason, optional expiry).
     */
    public function showSuspendForm(User $user): View|RedirectResponse
    {
        if ($user->id === (int) auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot suspend your own account.');
        }
        return view('admin.users.suspend', compact('user'));
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspend(User $user, ApiCacheService $cache): RedirectResponse
    {
        if ($user->id === (int) auth()->id()) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot unsuspend your own account.');
        }

        $reason = $user->suspended_reason;
        $user->is_suspended = false;
        $user->suspended_reason = null;
        $user->suspended_until = null;
        $user->save();

        $this->invalidateUserCaches($cache);
        $this->auditSuspension('unsuspend', $user, $reason, null);

        return redirect()->route('admin.users.index')->with('success', 'User unsuspended.');
    }

    private function invalidateUserCaches(ApiCacheService $cache): void
    {
        $cache->bumpVersion('profile');
        $cache->bumpVersion('feed');
        $cache->bumpVersion('reels');
    }

    private function auditSuspension(string $action, User $target, ?string $reason, $suspendedUntil): void
    {
        Log::channel('single')->info('user_suspension', [
            'admin_id' => auth()->id(),
            'target_user_id' => $target->id,
            'action' => $action,
            'reason' => $reason,
            'suspended_until' => $suspendedUntil?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

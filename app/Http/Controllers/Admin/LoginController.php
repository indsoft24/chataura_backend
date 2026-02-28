<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (session('admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = config('admin.email');
        $password = config('admin.password');
        $passwordHash = config('admin.password_hash');

        $emailMatch = $request->email === $email;
        $passwordMatch = $passwordHash
            ? Hash::check($request->password, $passwordHash)
            : ($request->password === $password);

        if (!$emailMatch || !$passwordMatch) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        session(['admin_authenticated' => true]);
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        session()->forget('admin_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}

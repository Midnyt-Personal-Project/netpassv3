<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;

class AuthController extends Controller
{
    /**
     * Show the unified login page
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectFor(Auth::user());
        }

        return view('auth.login');
    }

    private function redirectFor(User $user)
    {
        return $user->isSuperAdmin() ? redirect()->intended(route('superadmin.dashboard'))
            : redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Handle authentication attempts
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if ($user->status !== 'active') {
                Auth::logout();

                return back()->withErrors(['email' => 'This account is suspended. Please contact support.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            app(ActivityLogger::class)->record('auth.login', "{$user->email} signed in.", $user->id, $request->ip());

            return $this->redirectFor($user);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log out session
     */
    public function logout(Request $request)
    {
        if ($user = Auth::user()) {
            app(ActivityLogger::class)->record('auth.logout', "{$user->email} signed out.", $user->id, $request->ip());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Logged out successfully.');
    }
}

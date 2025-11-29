<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WebAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Use direct Laravel authentication
        if (Auth::attempt($request->only('email', 'password'))) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Update last login
            $user->update([
                'last_login_at' => now()
            ]);

            // Create token for API calls (if needed)
            $token = $user->createToken('web-token')->plainTextToken;

            // Store user in session
            session(['user' => $user, 'api_token' => $token]);

            return redirect()->route('dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout()
    {
        session()->forget(['api_token', 'user']);
        return redirect()->route('login');
    }
}

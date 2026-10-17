<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(private AuthManager $auth) {}

    public function show(Request $request): View
    {
        // The login page carries the email already typed there over as ?email= so it can
        // be prefilled here. Only accept a well-formed address; anything else opens the
        // page with a blank field. Blade escapes it on output.
        $email = $request->filled('email') && filter_var($request->query('email'), FILTER_VALIDATE_EMAIL)
            ? $request->query('email')
            : null;

        return view('auth.forgot-password', compact('email'));
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->auth->sendPasswordResetEmail($request->email);
        } catch (\Throwable $e) {
            // Always show success to prevent email enumeration
        }

        return back()->with('sent', true);
    }
}

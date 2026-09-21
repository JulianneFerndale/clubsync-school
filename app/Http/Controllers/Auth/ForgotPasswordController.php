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

    public function show(): View
    {
        return view('auth.forgot-password');
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

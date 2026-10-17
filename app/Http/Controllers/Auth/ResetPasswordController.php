<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * ClubSync's own "set a new password" page, reached from the reset link Firebase emails.
 *
 * The email carries a single-use oobCode (the proof the person owns the address); the
 * page where they choose the new password is entirely ClubSync's, not Firebase's hosted
 * one. Firebase mints and checks the code and stores the new password; ClubSync mirrors
 * the hash into the users table so password sign-in works at once — including for an
 * account that until now signed in with Google only (POLICY.md §Authentication).
 */
class ResetPasswordController extends Controller
{
    public function __construct(private AuthManager $auth) {}

    public function show(Request $request): View
    {
        $oobCode = (string) $request->query('oobCode', '');

        // Firebase routes every email action to one URL; only password resets belong here.
        if ($oobCode === '' || ($request->filled('mode') && $request->query('mode') !== 'resetPassword')) {
            return view('auth.reset-password', ['valid' => false, 'oobCode' => null, 'email' => null]);
        }

        try {
            $email = $this->auth->verifyPasswordResetCode($oobCode);
        } catch (\Throwable $e) {
            // Expired, already used, or malformed — never say which, and never echo the code.
            return view('auth.reset-password', ['valid' => false, 'oobCode' => null, 'email' => null]);
        }

        return view('auth.reset-password', ['valid' => true, 'oobCode' => $oobCode, 'email' => $email]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'oobCode'  => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'oobCode.required' => 'This reset link is incomplete. Open the link from your email again.',
        ]);

        try {
            $result = $this->auth->confirmPasswordReset($validated['oobCode'], $validated['password']);
        } catch (\Throwable $e) {
            // The code is single-use and time-limited; a second submit or a stale tab lands here.
            return back()->withErrors([
                'password' => 'This reset link has expired or was already used. Request a new one below.',
            ]);
        }

        // Mirror the new password into the users table so email sign-in works immediately.
        // The 'hashed' cast hashes it; an account that was Google-only now has a password
        // too, so it is no longer treated as Google-only (User::signsInWithGoogleOnly).
        if ($email = $result['email'] ?? null) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

            if ($user) {
                $user->forceFill([
                    'password'              => $validated['password'],
                    'last_password_changed' => now(),
                ])->save();

                AuditLog::record('auth.password.reset', $user, ['method' => 'email_link'], $user->id);
            } else {
                // Firebase accepted the reset but ClubSync has no such user: log it, do not fail.
                Log::warning('Password reset confirmed for an address with no ClubSync user: ' . $email);
            }
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in with your new password.');
    }
}

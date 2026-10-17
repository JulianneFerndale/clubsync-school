<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuthManager;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Authenticated password change, gated by POLICY.md's 7-day cooldown.
 */
class PasswordController extends Controller
{
    public function __construct(
        private AuthManager $auth,
        private SessionManager $session,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Registered with Google and never set a password: there is no current password
        // to confirm, so nothing here can be changed.
        if ($user->signsInWithGoogleOnly()) {
            return back()->withErrors([
                'password' => 'Your account signs in with your SCC Google account, so it has no ClubSync password to change.',
            ]);
        }

        // Cooldown is checked before validation so a user inside the window always
        // gets the date they can next change it, not a complexity complaint.
        if (! $user->canChangePassword()) {
            return back()->withErrors([
                'password' => 'You may only change your password once every '
                    . $user::PASSWORD_COOLDOWN_DAYS . ' days. You can change it again on '
                    . $user->passwordChangeAvailableAt()->format('F j, Y') . '.',
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        // Re-authenticate: proves the person at the keyboard owns the account (a
        // hijacked session alone must not be enough to lock the owner out), and
        // yields a known-fresh idToken for the update call below.
        try {
            $credentials = $this->auth->loginWithFirebase($user->email, $validated['current_password'], false);
        } catch (\Throwable $e) {
            return back()->withErrors(['current_password' => 'The password you entered is incorrect.']);
        }

        if ($validated['password'] === $validated['current_password']) {
            return back()->withErrors(['password' => 'Your new password must be different from your current password.']);
        }

        try {
            $refreshed = $this->auth->updatePassword($credentials['idToken'], $validated['password']);
        } catch (\Throwable $e) {
            return back()->withErrors(['password' => 'We could not update your password. Please try again.']);
        }

        $user->update([
            'password'              => $validated['password'], // 'hashed' cast handles hashing
            'last_password_changed' => now(),
        ]);

        // Keep this session alive on the new tokens. The "remember me" cookie holds a
        // refresh token Firebase just revoked, and the middleware prefers the cookie
        // over the session, so it has to go — the user re-authenticates next visit.
        $request->session()->regenerate();
        $this->session->store(
            $refreshed['idToken'] ?? $credentials['idToken'],
            $user->firebase_uid ?? $credentials['localId'],
            $user->role,
            $user->id,
            $user->is_admin,
            $refreshed['refreshToken'] ?? null,
        );
        cookie()->queue(cookie()->forget('clubsync_refresh'));

        AuditLog::record('auth.password.changed', $user, ['email' => $user->email], $user->id);

        return back()->with('success', 'Your password has been updated.');
    }
}

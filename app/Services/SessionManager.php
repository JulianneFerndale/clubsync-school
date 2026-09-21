<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class SessionManager
{
    /**
     * Store Firebase auth data in the Laravel session.
     */
    public function store(string $idToken, string $uid, string $role, int $userId, bool $isAdmin = false, ?string $refreshToken = null): void
    {
        session([
            'firebase_id_token'  => $idToken,
            'firebase_uid'       => $uid,
            'firebase_user_role' => $role,
            'firebase_user_id'   => $userId,
            'firebase_is_admin'  => $isAdmin,
        ]);

        // Persist the refresh token for the lifetime of the session so an active
        // user's expired ID token (Firebase tokens last ~1 hour) can be silently
        // refreshed instead of being forced to log out mid-task. "Remember Me"
        // additionally stores it in a 30-day cookie for cross-session persistence.
        if ($refreshToken !== null) {
            session(['firebase_refresh_token' => $refreshToken]);
        }
    }

    /**
     * Refresh only the stored ID token (after a silent token refresh).
     */
    public function updateToken(string $idToken): void
    {
        session(['firebase_id_token' => $idToken]);
    }

    /**
     * Return the appropriate dashboard redirect based on the user's role.
     */
    public function redirectByRole(string $role): RedirectResponse
    {
        return match ($role) {
            'dsa'     => Redirect::route('dsa.dashboard'),
            'adviser' => Redirect::route('adviser.dashboard'),
            // Officers navigate like members; club-admin tools surface inside their own club.
            default   => Redirect::route('member.dashboard'),
        };
    }
}

<?php

namespace App\Services;

use App\Models\EnrollmentVerification;
use App\Models\User;
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
     * Open the session of a user Firebase has just signed in — by password or by
     * Google — and send them where they belong. The caller has already refused a
     * suspended account.
     *
     * @param  array  $firebaseData  Firebase's sign-in answer (localId, idToken, refreshToken)
     */
    public function signIn(User $user, array $firebaseData): RedirectResponse
    {
        // Sync firebase_uid if this user pre-dates the Firebase migration
        if (! $user->firebase_uid) {
            $user->update(['firebase_uid' => $firebaseData['localId']]);
        }

        request()->session()->regenerate();

        $this->store(
            $firebaseData['idToken'],
            $firebaseData['localId'],
            $user->role,
            $user->id,
            $user->is_admin,
            $firebaseData['refreshToken'] ?? null,
        );

        // Answered here so RequireCourseSelection does not have to ask the database
        // on every request of the session.
        session(['course_selected' => (bool) $user->course_id]);

        // Likewise for RequireEnrollmentVerification. A student still waiting on the DSA
        // is not cached, so the gate looks again — and lets them in once approved.
        $held = in_array($user->enrollment_status, [EnrollmentVerification::STATUS_PENDING, EnrollmentVerification::STATUS_REJECTED], true);

        session(['enrollment_cleared' => ! $held]);

        // Confirmed on the page they land on by <x-toast />, which leaves by itself.
        session()->flash('toast', [
            'title' => 'Logged in successfully',
            'text'  => 'Welcome back, ' . $user->first_name . '!',
        ]);

        if ($held) {
            return Redirect::route('verification.show');
        }

        return $this->redirectByRole($user->role);
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
            'vpa'     => Redirect::route('vpa.dashboard'),
            'adviser' => Redirect::route('adviser.dashboard'),
            // Officers navigate like members; club-admin tools surface inside their own club.
            default   => Redirect::route('member.dashboard'),
        };
    }
}

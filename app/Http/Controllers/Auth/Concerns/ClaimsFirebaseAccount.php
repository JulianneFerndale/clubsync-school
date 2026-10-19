<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Exceptions\EmailAlreadyRegistered;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Creating the Firebase account for a new registration, shared by the student wizard and
 * adviser sign-up so both settle a taken address the same way (POLICY.md §Registration).
 * Expects $this->auth, the AuthManager.
 */
trait ClaimsFirebaseAccount
{
    /**
     * The Firebase account this registration will use, created if the address is free.
     *
     * Firebase will not answer "is this address taken?" — email enumeration protection
     * withholds `registered` from accounts:createAuthUri, and a real address and a
     * made-up one come back byte-identical. It will, however, refuse to create a
     * duplicate. So creating the account IS the check, and it runs at the email field.
     *
     * A refusal means two different things to the person typing:
     *   - a ClubSync account exists too → the address really is spoken for; sign in.
     *   - no ClubSync account exists → an earlier attempt created the Firebase half
     *     and never finished. That half is theirs, so if the password they just typed
     *     opens it they are handed it back and carry on from where they left off.
     *     If it does not open, the address belongs to somebody else.
     *
     * @throws EmailAlreadyRegistered when the address is not this person's to register
     */
    private function claimFirebaseAccount(string $email, string $password): array
    {
        try {
            return $this->auth->registerWithFirebase($email, $password);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'EMAIL_EXISTS')) {
                throw $e;
            }
        }

        if (User::where('email', $email)->exists()) {
            throw new EmailAlreadyRegistered(
                'An account with this email address already exists. Please sign in instead.'
            );
        }

        // Half-finished registration. Adopt it only if this person can open it.
        try {
            $account = $this->auth->loginWithFirebase($email, $password, remember: false);
        } catch (\Throwable $e) {
            throw new EmailAlreadyRegistered(
                'This email address is already registered. Please sign in instead, or reset your password if you have forgotten it. '
                . 'If you started registering with Google, tap Register with Google to finish.'
            );
        }

        Log::info('Resumed an unfinished registration for ' . $email);

        return $account;
    }
}

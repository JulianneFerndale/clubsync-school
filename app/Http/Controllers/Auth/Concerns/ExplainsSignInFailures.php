<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Why a sign-in did not go through, in words the person can act on (POLICY.md
 * §Authentication). Shared by the password form and the step that links a Google
 * account, so the two can never describe the same failure differently.
 */
trait ExplainsSignInFailures
{
    /**
     * The server could not reach the database or Firebase. On a local install that
     * is this computer's internet; either way it is not the person's email or password.
     */
    private const CONNECTION_FAILED = 'ClubSync could not connect. Please check your internet connection and try again.';

    private const SUSPENDED = 'Your account has been suspended. Please contact the Director of Student Affairs for assistance.';

    /** Signing in to a registration that stopped before its proof of enrollment. */
    private const RESUMED = 'Welcome back! You have not finished registering yet, so you are back at the step where you stopped.';

    /**
     * Why Firebase turned down a registered address's password.
     *
     * AuthManager throws Firebase's error code as the message, sometimes followed by
     * " : <detail>" (TOO_MANY_ATTEMPTS_TRY_LATER), so only the code before it is read.
     * Anything unrecognised — a bad API key, Firebase itself failing — is not the
     * person's doing, so it is logged and reported as the service being unavailable.
     */
    private function firebaseRefusal(\Throwable $e): RedirectResponse
    {
        $code = trim(strtok($e->getMessage(), ':'));

        return match ($code) {
            'INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD' => back()->withErrors([
                'password' => 'The password you entered is incorrect. Try again, or tap Forgot Password to reset it.',
            ]),
            'TOO_MANY_ATTEMPTS_TRY_LATER' => back()->withErrors([
                'password' => 'Too many wrong passwords for this account. Please wait a few minutes, or tap Forgot Password to reset it.',
            ]),
            'USER_DISABLED' => back()->withErrors([
                'email' => self::SUSPENDED,
            ]),
            'INVALID_EMAIL' => back()->withErrors([
                'email' => 'Please enter a valid email address.',
            ]),
            // In ClubSync's records but missing from Firebase — only an administrator can mend that.
            'EMAIL_NOT_FOUND' => back()->withErrors([
                'email' => 'This account cannot sign in yet. Please contact the ClubSync administrator.',
            ]),
            default => tap(back()->with('error', 'Sign-in is unavailable right now. Please try again in a few minutes.'), function () use ($code) {
                Log::error('Firebase sign-in failed for a reason the person cannot fix.', ['code' => $code]);
            }),
        };
    }
}

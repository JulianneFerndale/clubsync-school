<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthManager
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('firebase.api_key');
    }

    /**
     * Sign in with Firebase using email/password.
     * Returns the full Firebase response array on success.
     * Throws on invalid credentials or Firebase error.
     */
    public function loginWithFirebase(string $email, string $password, bool $remember): array
    {
        $response = Http::post(config('firebase.sign_in_url') . '?key=' . $this->apiKey, [
            'email'             => $email,
            'password'          => $password,
            'returnSecureToken' => true,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'UNKNOWN_ERROR');
            throw new \RuntimeException($error);
        }

        $data = $response->json();

        if ($remember) {
            cookie()->queue(
                cookie('clubsync_refresh', $data['refreshToken'], 60 * 24 * 30, '/', null, true, true, false, 'Strict')
            );
        }

        return $data;
    }

    /**
     * Create a new Firebase Auth user (used during registration).
     */
    public function registerWithFirebase(string $email, string $password): array
    {
        $response = Http::post(config('firebase.sign_up_url') . '?key=' . $this->apiKey, [
            'email'             => $email,
            'password'          => $password,
            'returnSecureToken' => true,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'UNKNOWN_ERROR');
            throw new \RuntimeException($error);
        }

        return $response->json();
    }

    /**
     * Resolve the role for a given Firebase UID from MySQL.
     */
    public function resolveUserRole(string $uid): string
    {
        return User::where('firebase_uid', $uid)->value('role') ?? 'member';
    }

    /**
     * Use a refresh token to get a new ID token.
     * Returns the new idToken on success, throws on failure.
     */
    public function refreshIdToken(string $refreshToken): string
    {
        $response = Http::asForm()->post(config('firebase.refresh_url') . '?key=' . $this->apiKey, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('TOKEN_REFRESH_FAILED');
        }

        return $response->json('id_token');
    }

    /**
     * Decode the JWT payload and return the claims array.
     * Does NOT verify the signature — used only to read exp/uid.
     */
    public function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));

        return json_decode($payload, true) ?? [];
    }

    /**
     * Returns true if the given idToken is expired (or unreadable).
     */
    public function isTokenExpired(string $idToken): bool
    {
        $claims = $this->decodeIdToken($idToken);

        if (empty($claims['exp'])) {
            return true;
        }

        return time() >= (int) $claims['exp'];
    }

    /**
     * Verify the idToken by calling Firebase lookup (validates server-side).
     * Returns the account data array on success, throws on failure.
     */
    public function verifyIdToken(string $idToken): array
    {
        $response = Http::post(config('firebase.lookup_url') . '?key=' . $this->apiKey, [
            'idToken' => $idToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('TOKEN_INVALID');
        }

        $users = $response->json('users');

        if (empty($users)) {
            throw new \RuntimeException('TOKEN_INVALID');
        }

        return $users[0];
    }

    /**
     * Update the Firebase Auth password for a given user.
     *
     * Firebase revokes every previously issued token for the account, so the
     * caller must persist the fresh idToken/refreshToken returned here or the
     * user's current session dies on its next token refresh.
     */
    public function updatePassword(string $idToken, string $newPassword): array
    {
        $response = Http::post(config('firebase.update_url') . '?key=' . $this->apiKey, [
            'idToken'           => $idToken,
            'password'          => $newPassword,
            'returnSecureToken' => true,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'UPDATE_FAILED');
            throw new \RuntimeException($error);
        }

        return $response->json();
    }

    /**
     * Send a Firebase password-reset email to the given address.
     * Throws if Firebase returns an error (e.g. EMAIL_NOT_FOUND).
     */
    public function sendPasswordResetEmail(string $email): void
    {
        $response = Http::post(config('firebase.send_oob_code_url') . '?key=' . $this->apiKey, [
            'requestType' => 'PASSWORD_RESET',
            'email'       => $email,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'UNKNOWN_ERROR');
            throw new \RuntimeException($error);
        }
    }

    /**
     * Clear the session and refresh token cookie.
     */
    public function revokeSession(): void
    {
        session()->forget([
            'firebase_id_token',
            'firebase_uid',
            'firebase_user_role',
            'firebase_user_id',
            'firebase_is_admin',
            'firebase_refresh_token',
        ]);

        cookie()->queue(cookie()->forget('clubsync_refresh'));
    }
}

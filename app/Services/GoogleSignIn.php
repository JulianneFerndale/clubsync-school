<?php

namespace App\Services;

use App\Exceptions\GoogleSignInRefused;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Continue with Google", for Saint Columban College Google Workspace accounts only.
 *
 * An OAuth 2.0 authorization-code flow run by the server, with state, nonce and PKCE.
 * Google's answer is judged here — the Workspace it comes from and whether the address
 * is verified — BEFORE anything is sent to Firebase, so a Google account outside the
 * school never gets as far as having a Firebase account made for it.
 */
class GoogleSignIn
{
    /**
     * The only Google Workspace whose accounts may sign in (POLICY.md §Authentication).
     * Fixed here rather than read from .env, so a configuration slip cannot widen it.
     */
    public const DOMAIN = 'sccpag.edu.ph';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const TRY_AGAIN = 'Google sign-in could not be completed. Please try again.';

    public function isConfigured(): bool
    {
        return filled(config('google.sign_in.client_id')) && filled(config('google.sign_in.client_secret'));
    }

    /**
     * Where to send the browser, and the one-time values the callback must see again.
     *
     * @return array{url: string, state: string, nonce: string, verifier: string}
     */
    public function start(): array
    {
        $state    = Str::random(40);
        $nonce    = Str::random(40);
        $verifier = Str::random(64);

        $query = http_build_query([
            'client_id'             => config('google.sign_in.client_id'),
            'redirect_uri'          => $this->redirectUri(),
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            // Narrows Google's account chooser to SCC accounts. Only a hint — anyone can
            // edit the address bar — which is why identity() checks the hd claim itself.
            'hd'                    => self::DOMAIN,
            'prompt'                => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url'      => self::AUTHORIZE_URL . '?' . $query,
            'state'    => $state,
            'nonce'    => $nonce,
            'verifier' => $verifier,
        ];
    }

    /**
     * Trade the code Google sent back for the person's Google identity, and refuse it
     * unless it is a verified account of the SCC Google Workspace.
     *
     * @return array{id_token: string, email: string, first_name: ?string, last_name: ?string}
     *
     * @throws GoogleSignInRefused with a message the person can act on
     * @throws \Illuminate\Http\Client\ConnectionException when Google cannot be reached
     */
    public function identity(string $code, string $verifier, string $nonce): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('google.sign_in.client_id'),
            'client_secret' => config('google.sign_in.client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
            'code_verifier' => $verifier,
        ]);

        $idToken = $response->json('id_token');

        if ($response->failed() || ! is_string($idToken)) {
            // invalid_client / redirect_uri_mismatch are setup faults, not the person's.
            Log::warning('Google sign-in: the code exchange failed.', ['error' => $response->json('error')]);

            throw new GoogleSignInRefused(self::TRY_AGAIN);
        }

        // The token came straight from Google's token endpoint over TLS, in exchange for
        // this app's client secret, so its signature need not be checked here (Google's
        // OpenID Connect guide says so; Firebase checks it again at sign-in regardless).
        // Its claims are what decide who this is.
        $claims = $this->claims($idToken);

        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ($claims['aud'] ?? null) !== config('google.sign_in.client_id')
            || (int) ($claims['exp'] ?? 0) <= time()
            || ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            Log::warning('Google sign-in: the ID token does not belong to this sign-in.');

            throw new GoogleSignInRefused(self::TRY_AGAIN);
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        // hd is present only on an account managed by a Google Workspace, and names that
        // Workspace. The address alone proves nothing: a personal Google account can be
        // opened with any address, one that looks like ours included.
        if (($claims['hd'] ?? null) !== self::DOMAIN || ! str_ends_with($email, '@' . self::DOMAIN)) {
            Log::info('Google sign-in refused an account outside the SCC Workspace.', ['hd' => $claims['hd'] ?? null]);

            throw new GoogleSignInRefused(
                'Only Saint Columban College Google accounts (@sccpag.edu.ph) can be used with ClubSync. '
                . ($email !== '' ? "{$email} is not one. " : '')
                . 'Please choose your SCC account and try again.'
            );
        }

        if (! in_array($claims['email_verified'] ?? null, [true, 'true'], true)) {
            throw new GoogleSignInRefused(
                'Google has not verified this SCC email address yet. Please sign in with your email and password instead.'
            );
        }

        return [
            'id_token'   => $idToken,
            'email'      => $email,
            'first_name' => $claims['given_name'] ?? null,
            'last_name'  => $claims['family_name'] ?? null,
        ];
    }

    private function redirectUri(): string
    {
        return config('google.sign_in.redirect_uri') ?: route('auth.google.callback');
    }

    /** The payload of a JWT, unverified. */
    private function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
    }
}

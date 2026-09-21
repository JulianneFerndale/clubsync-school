<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal, dependency-free Google Calendar client.
 *
 * Authenticates as a Google Workspace service account using domain-wide
 * delegation (a signed JWT exchanged for an OAuth access token) and talks to
 * the Calendar v3 REST API directly — no google/apiclient package required.
 *
 * Every call acts *as* one Workspace user ($subject) and writes to that user's
 * own primary calendar, which is how a ClubSync event lands in the personal
 * Google Calendar of each member it concerns.
 *
 * Callers check enabled() first. API errors throw, so the queued sync job can
 * retry; a 404/410 on update is not an error but a sign the user deleted the
 * event, reported as false so the caller can recreate it.
 */
class GoogleCalendarService
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private array $config;

    public function __construct()
    {
        $this->config = config('google.calendar');
    }

    public function enabled(): bool
    {
        return ($this->config['enabled'] ?? false)
            && ! empty($this->config['credentials'])
            && is_file($this->config['credentials']);
    }

    /**
     * Whether $email is an account the service account may act as. Only the
     * institutional Workspace domain is — POLICY.md § External Integrations.
     */
    public function canSyncFor(?string $email): bool
    {
        $domain = strtolower((string) ($this->config['domain'] ?? ''));

        return $domain !== '' && $email !== null
            && str_ends_with(strtolower($email), '@' . $domain);
    }

    /** Create an event on $subject's calendar and return its Google event id. */
    public function createEvent(string $subject, array $event): string
    {
        $response = $this->client($subject)->post(self::API_BASE, $event);

        $this->throwUnlessSuccessful($response, 'create');

        return (string) $response->json('id');
    }

    /** Update an event; false when it no longer exists on $subject's calendar. */
    public function updateEvent(string $subject, string $eventId, array $event): bool
    {
        $response = $this->client($subject)->put(self::API_BASE . '/' . rawurlencode($eventId), $event);

        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }

        $this->throwUnlessSuccessful($response, 'update');

        return true;
    }

    /** Delete an event. Already gone (404/410) counts as deleted. */
    public function deleteEvent(string $subject, string $eventId): void
    {
        $response = $this->client($subject)->delete(self::API_BASE . '/' . rawurlencode($eventId));

        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $this->throwUnlessSuccessful($response, 'delete');
    }

    private function client(string $subject)
    {
        return Http::withToken($this->accessToken($subject))->timeout(15);
    }

    private function throwUnlessSuccessful(Response $response, string $action): void
    {
        if (! $response->successful()) {
            throw new RuntimeException("Google Calendar {$action} failed ({$response->status()}): {$response->body()}");
        }
    }

    /** Fetch (and cache) an OAuth access token for $subject via the service-account JWT grant. */
    private function accessToken(string $subject): string
    {
        return Cache::remember('google_calendar_token:' . sha1($subject), now()->addMinutes(50), function () use ($subject) {
            $key = json_decode((string) file_get_contents($this->config['credentials']), true);

            if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
                throw new RuntimeException('Invalid Google service-account key file.');
            }

            $tokenUri = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            $response = Http::asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $this->buildSignedJwt($key, $tokenUri, $subject),
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException("Google token exchange failed for {$subject}: " . $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function buildSignedJwt(array $key, string $tokenUri, string $subject): string
    {
        $now = time();

        $segments = [
            $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode([
                'iss'   => $key['client_email'],
                'sub'   => $subject, // domain-wide delegation: act as this Workspace user
                'scope' => self::SCOPE,
                'aud'   => $tokenUri,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ])),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign Google JWT with the service-account private key.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

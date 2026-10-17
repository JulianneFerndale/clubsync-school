<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort push to Reverb that never makes the person wait on it.
 *
 * Not every deployment runs Reverb (the Render image has none, and a local run
 * without `composer run dev` has none either). Against an unreachable server one
 * broadcast blocked for about five seconds — "localhost" is tried over IPv6 and then
 * IPv4 — and a membership decision that notifies four people took twenty. So a
 * failure marks the server down for a minute and every push in that minute is
 * skipped. Nothing is lost: the notification row is the system of record, and the
 * page polls for it while its socket is down (resources/js/app.js).
 */
final class LiveBroadcast
{
    private const DOWN_KEY = 'live-broadcast:down-until';

    private const DOWN_SECONDS = 60;

    /**
     * Unix time until which pushes are skipped; null until first read. Read from the
     * cache once per process, so a request that pushes to four people asks once.
     */
    private static ?int $downUntil = null;

    public static function send(object $event, string $context): void
    {
        if (self::isDown()) {
            return;
        }

        try {
            broadcast($event);
        } catch (\Throwable $e) {
            self::$downUntil = time() + self::DOWN_SECONDS;
            Cache::put(self::DOWN_KEY, self::$downUntil, self::DOWN_SECONDS);
            Log::warning("{$context} broadcast failed; live pushes paused for " . self::DOWN_SECONDS . 's: ' . $e->getMessage());
        }
    }

    public static function isDown(): bool
    {
        self::$downUntil ??= (int) Cache::get(self::DOWN_KEY, 0);

        return self::$downUntil > time();
    }

    /** Try pushing again now, e.g. once Reverb has been started (and between tests). */
    public static function resume(): void
    {
        self::$downUntil = null;
        Cache::forget(self::DOWN_KEY);
    }
}

<?php

namespace App\Jobs;

use App\Services\GoogleCalendarSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bring a handful of users' Google Calendar copies of one record in line with
 * the database. Users whose sync failed are retried on their own, a few minutes
 * later, so one unreachable account never re-runs the whole batch.
 */
class SyncGoogleCalendarUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ATTEMPTS = 3;

    /** @param  list<int>  $userIds */
    public function __construct(
        private string $sourceType,
        private int $sourceId,
        private array $userIds,
        private int $attempt = 1,
    ) {}

    public function handle(GoogleCalendarSync $sync): void
    {
        $failed = $sync->syncUsers($this->sourceType, $this->sourceId, $this->userIds);

        if ($failed === []) {
            return;
        }

        if ($this->attempt >= self::MAX_ATTEMPTS) {
            Log::error("Google Calendar sync gave up on {$this->sourceType} #{$this->sourceId} after "
                . self::MAX_ATTEMPTS . ' attempts for user(s): ' . implode(', ', $failed));

            return;
        }

        self::dispatch($this->sourceType, $this->sourceId, $failed, $this->attempt + 1)
            ->delay(now()->addMinutes(5 * $this->attempt));
    }
}

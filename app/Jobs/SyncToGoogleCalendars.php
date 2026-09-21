<?php

namespace App\Jobs;

use App\Models\GoogleCalendarEvent;
use App\Services\GoogleCalendarService;
use App\Services\GoogleCalendarSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A record changed (activity, fee or semester): mirror that change into the
 * Google Calendar of everyone it concerns — and out of the calendar of anyone it
 * no longer does. Dispatch it after any change; it works out the rest.
 *
 * Fans out to small per-user batches so a semester that reaches every student
 * never becomes one long job that times out.
 */
class SyncToGoogleCalendars implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATCH_SIZE = 25;

    public function __construct(private string $sourceType, private int $sourceId) {}

    public function handle(GoogleCalendarService $calendar, GoogleCalendarSync $sync): void
    {
        if (! $calendar->enabled()) {
            return;
        }

        // Everyone who should have it, plus everyone who has it and may need it removed.
        $userIds = $sync->recipientIds($this->sourceType, $this->sourceId)
            ->merge(GoogleCalendarEvent::where('source_type', $this->sourceType)
                ->where('source_id', $this->sourceId)
                ->pluck('user_id'))
            ->unique()
            ->values();

        foreach ($userIds->chunk(self::BATCH_SIZE) as $batch) {
            SyncGoogleCalendarUsers::dispatch($this->sourceType, $this->sourceId, $batch->values()->all());
        }
    }
}

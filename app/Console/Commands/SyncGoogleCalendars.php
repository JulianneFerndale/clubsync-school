<?php

namespace App\Console\Commands;

use App\Jobs\SyncToGoogleCalendars;
use App\Models\ClubActivity;
use App\Models\Fee;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

/**
 * Push what is already in ClubSync onto users' Google Calendars.
 *
 * Day-to-day, every change syncs itself. Run this once after switching sync on,
 * or after restoring the key, to catch up on everything created in the meantime.
 * Safe to repeat: unchanged copies are skipped.
 */
class SyncGoogleCalendars extends Command
{
    protected $signature = 'google-calendar:sync
                            {--only=* : Limit to activities, fees and/or semesters}';

    protected $description = 'Queue a Google Calendar sync of every upcoming activity, fee due date and semester.';

    private const OPTIONS = [
        'activities' => GoogleCalendarEvent::ACTIVITY,
        'fees'       => GoogleCalendarEvent::FEE,
        'semesters'  => GoogleCalendarEvent::SEMESTER,
    ];

    public function handle(GoogleCalendarService $calendar): int
    {
        if (! $calendar->enabled()) {
            $this->error('Google Calendar sync is off. Set GOOGLE_CALENDAR_ENABLED=true and place the service-account key first.');

            return self::FAILURE;
        }

        $only = $this->option('only') ?: array_keys(self::OPTIONS);

        if ($unknown = array_diff($only, array_keys(self::OPTIONS))) {
            $this->error('Unknown --only value(s): ' . implode(', ', $unknown) . '. Use activities, fees or semesters.');

            return self::FAILURE;
        }

        foreach ($only as $option) {
            $type = self::OPTIONS[$option];
            $ids  = $this->upcoming($type);

            $ids->each(fn (int $id) => SyncToGoogleCalendars::dispatch($type, $id));

            $this->info("Queued {$ids->count()} {$option}.");
        }

        $this->line('The queue worker does the rest; failures are written to the log.');

        return self::SUCCESS;
    }

    private function upcoming(string $type)
    {
        return match ($type) {
            GoogleCalendarEvent::ACTIVITY => ClubActivity::whereDate('date', '>=', today())
                ->whereIn('approval_status', ClubActivity::CONFIRMED_APPROVAL_STATUSES)
                ->where('status', '!=', 'cancelled')
                ->pluck('id'),
            GoogleCalendarEvent::FEE      => Fee::whereDate('due_date', '>=', today())->pluck('id'),
            GoogleCalendarEvent::SEMESTER => Semester::whereDate('end_date', '>=', today())->pluck('id'),
        };
    }
}

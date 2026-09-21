<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\GoogleCalendarEvent;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Someone's place in a club changed (approved, rejected, left, or became or
 * stopped being its adviser): add the club's upcoming activities to their
 * Google Calendar, or take the ones they have back off.
 */
class SyncClubMemberGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $userId, private int $clubId) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        if (! $calendar->enabled()) {
            return;
        }

        // Past activities are left alone: a new member does not need last month's meeting.
        $upcoming = ClubActivity::where('club_id', $this->clubId)
            ->whereDate('date', '>=', today())
            ->whereIn('approval_status', ClubActivity::CONFIRMED_APPROVAL_STATUSES)
            ->where('status', '!=', 'cancelled')
            ->pluck('id');

        $alreadyMirrored = GoogleCalendarEvent::where('user_id', $this->userId)
            ->where('source_type', GoogleCalendarEvent::ACTIVITY)
            ->whereIn('source_id', ClubActivity::where('club_id', $this->clubId)->select('id'))
            ->pluck('source_id');

        foreach ($upcoming->merge($alreadyMirrored)->unique() as $activityId) {
            SyncGoogleCalendarUsers::dispatch(GoogleCalendarEvent::ACTIVITY, $activityId, [$this->userId]);
        }
    }
}

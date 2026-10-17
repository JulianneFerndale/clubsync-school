<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\ClubActivity;
use App\Models\ClubNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell a member that an officer recorded their attendance: checked in, checked out, or
 * a record removed. When the activity is marked completed, its attendance is closed and
 * everyone who was checked in is told what their record finally says.
 *
 * Each notification is persisted and, through ClubNotification's created hook, pushed to
 * the member live, so the toast arrives and an open My Activity page refreshes itself.
 *
 * The message is written from the attendance record as it is when the job runs, not as
 * it was when the officer clicked: a check-in that was undone before the job ran is not
 * announced, and a removal is not announced once the member has been checked in again.
 */
class NotifyMemberOfAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CHECKED_IN  = 'in';
    public const CHECKED_OUT = 'out';
    public const REMOVED     = 'removed';
    public const CLOSED      = 'closed';

    /**
     * @param  string    $reason   One of the constants above.
     * @param  int|null  $userId   The member, for every reason but CLOSED (which tells every attendee).
     * @param  int|null  $actorId  The officer who acted — they are not told about their own attendance.
     */
    public function __construct(
        private int $activityId,
        private string $reason,
        private ?int $userId = null,
        private ?int $actorId = null,
    ) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity?->club) {
            return;
        }

        if ($this->reason === self::CLOSED) {
            Attendance::where('event_id', $activity->id)
                ->whereNotNull('time_in')
                ->when($this->actorId, fn ($q) => $q->where('user_id', '!=', $this->actorId))
                ->each(fn (Attendance $record) => $this->notify($activity, $record->user_id, $record));

            return;
        }

        if (! $this->userId || $this->userId === $this->actorId) {
            return;
        }

        $record = Attendance::where('event_id', $activity->id)->where('user_id', $this->userId)->first();

        $current = match ($this->reason) {
            self::CHECKED_IN  => $record?->time_in !== null,
            self::CHECKED_OUT => $record?->time_out !== null,
            self::REMOVED     => $record === null,
            default           => false,
        };

        if ($current) {
            $this->notify($activity, $this->userId, $record);
        }
    }

    private function notify(ClubActivity $activity, int $userId, ?Attendance $record): void
    {
        [$title, $body] = $this->message($activity, $record);

        ClubNotification::create([
            'recipient_id' => $userId,
            'sender_type'  => 'system',
            'club_id'      => $activity->club_id,
            'title'        => $title,
            'body'         => $body,
            'action_url'   => route('member.activity', ['tab' => 'attendance']),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function message(ClubActivity $activity, ?Attendance $record): array
    {
        $club  = $activity->club->acronym ?: $activity->club->name;
        $event = "\"{$activity->title}\" ({$club})";
        $in    = $record?->time_in?->format('g:i A');
        $out   = $record?->time_out?->format('g:i A');
        $on    = $record?->time_in?->format('M j');

        return match ($this->reason) {
            self::CHECKED_IN => [
                'Checked in to attendance',
                "You were checked in to {$event} at {$in} on {$on}.",
            ],
            self::CHECKED_OUT => [
                'Checked out of attendance',
                "You were checked out of {$event} at {$out}. Your attendance is recorded: in {$in}, out {$out}.",
            ],
            self::REMOVED => [
                'Attendance record removed',
                "An officer removed your attendance record for {$event}. If you were there, ask them to record it again.",
            ],
            default => [
                'Attendance concluded',
                "The attendance for {$event} has closed. Your record: "
                    . ($out ? "checked in {$in}, checked out {$out}." : "checked in {$in}; no check-out was recorded."),
            ],
        };
    }
}

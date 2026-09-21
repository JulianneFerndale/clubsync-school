<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell a club's active members that an activity was scheduled or changed.
 *
 * Each notification is persisted and, through ClubNotification's created hook,
 * pushed to the member over Reverb — so a connected member sees it without
 * refreshing the page.
 *
 * Only *confirmed* activities are announced. An activity still awaiting DSA
 * sign-off is not confirmed (POLICY.md § Event Creation), so members hear about
 * it when the DSA approves, not when it is submitted.
 */
class NotifyMembersOfActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SCHEDULED = 'scheduled';
    public const UPDATED   = 'updated';
    public const APPROVED  = 'approved';

    /**
     * @param  string    $reason         One of SCHEDULED, UPDATED or APPROVED.
     * @param  int|null  $excludeUserId  The officer who made the change — they do
     *                                   not need telling about their own edit.
     */
    public function __construct(
        private int $activityId,
        private string $reason = self::SCHEDULED,
        private ?int $excludeUserId = null,
    ) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity?->club || ! $activity->isConfirmed()) {
            return;
        }

        $club = $activity->club;
        $url  = route('member.clubs.show', $club);

        [$title, $body] = $this->message($activity, $club->name);

        $members = ClubMember::where('club_id', $club->id)
            ->where('status', 'active')
            ->when($this->excludeUserId, fn ($q) => $q->where('user_id', '!=', $this->excludeUserId))
            ->with('user:id,year_level');

        $members->chunkById(200, function ($chunk) use ($activity, $club, $title, $body, $url) {
            foreach ($chunk as $member) {
                if (! $member->user || ! $activity->reachesYearLevel($member->user->year_level)) {
                    continue;
                }

                ClubNotification::create([
                    'recipient_id' => $member->user_id,
                    'sender_type'  => 'system',
                    'club_id'      => $club->id,
                    'title'        => $title,
                    'body'         => $body,
                    'action_url'   => $url,
                ]);
            }
        });
    }

    /** @return array{0: string, 1: string} */
    private function message(ClubActivity $activity, string $clubName): array
    {
        $when  = $activity->date->format('M j, Y');
        $slot  = substr((string) $activity->time_start, 0, 5) . '–' . substr((string) $activity->time_end, 0, 5);
        $where = $activity->venue;

        $title = match ($this->reason) {
            self::UPDATED  => $clubName . ' updated an activity',
            self::APPROVED => $clubName . ' had an activity approved',
            default        => $clubName . ' scheduled a new activity',
        };

        $lead = $this->reason === self::UPDATED ? 'now' : 'on';

        return [$title, "\"{$activity->title}\" — {$lead} {$when}, {$slot} at {$where}."];
    }
}

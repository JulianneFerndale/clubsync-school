<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell a club's officers and adviser what became of an activity sent for approval: the
 * adviser's decision on its room booking letter, then the VPA's (POLICY.md §Event
 * Creation). An adviser's approval is news, not the end — it is now with the VPA.
 */
class NotifyOfficersOfActivityDecision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ADVISER_APPROVED = 'adviser_approved';

    public const VPA_APPROVED = 'vpa_approved';
    public const VPA_REJECTED = 'vpa_rejected';

    public function __construct(private int $activityId, private string $decision) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity || ! $activity->club) {
            return;
        }

        $club = $activity->club;

        $recipientIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id');

        // The adviser hears of every decision but their own approval, which they just gave.
        if ($club->adviser_id && $this->decision !== self::ADVISER_APPROVED) {
            $recipientIds->push($club->adviser_id);
        }

        $remarks = $activity->review_remarks ? ' Remarks: ' . $activity->review_remarks : '';

        [$title, $body] = match ($this->decision) {
            self::ADVISER_APPROVED => [
                'Letter approved by the adviser',
                "The club adviser approved and signed the room booking letter for \"{$activity->title}\". It is now with the Vice President for Administration for final approval.",
            ],
            self::VPA_APPROVED => [
                'Activity approved',
                "The Vice President for Administration approved the room booking letter for \"{$activity->title}\". The activity is confirmed and members have been told.",
            ],
            self::VPA_REJECTED => [
                'Letter returned by the VPA',
                "The Vice President for Administration did not approve the room booking letter for \"{$activity->title}\".{$remarks} Edit the activity to send it again.",
            ],
            'approved' => [
                'Activity approved',
                "\"{$activity->title}\" was approved by the club adviser.",
            ],
            default => [
                'Activity rejected',
                "\"{$activity->title}\" was rejected by the club adviser.{$remarks}",
            ],
        };

        foreach ($recipientIds->unique() as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => $title,
                'body'         => $body,
                'action_url'   => (int) $recipientId === (int) $club->adviser_id
                    ? route('adviser.activities.show', $activity)
                    : route('officer.activities.show', $activity),
            ]);
        }
    }
}

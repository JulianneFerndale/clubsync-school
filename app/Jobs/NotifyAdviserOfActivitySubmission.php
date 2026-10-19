<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell a club's adviser that an officer submitted an activity for review.
 *
 * The adviser's queue is where every new activity goes first, before the VPA (POLICY.md
 * §Event Creation); this notification is what brings them to it, and per
 * POLICY.md §Notifications the in-system notification is the system of record.
 *
 * A club with no adviser would otherwise leave the activity waiting on nobody, so
 * the DSA is told instead — the same fallback POLICY sets for announcements ("If no
 * active Adviser is assigned to a club, hold submissions in queue and alert the DSA
 * Admin"). The activity still waits; only the person being nudged changes.
 */
class NotifyAdviserOfActivitySubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $activityId) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity?->club || $activity->approval_status !== 'pending_approval') {
            return;
        }

        $club = $activity->club;

        if ($club->adviser_id) {
            ClubNotification::create([
                'recipient_id' => $club->adviser_id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Activity to review',
                'body'         => "{$club->name} submitted \"{$activity->title}\" for your review.",
                'action_url'   => route('adviser.activities.review'),
            ]);

            return;
        }

        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Activity waiting — no adviser assigned',
                'body'         => "{$club->name} submitted \"{$activity->title}\", but the club has no adviser to review it.",
                'action_url'   => route('dsa.activities.monitor'),
            ]);
        }
    }
}

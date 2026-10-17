<?php

namespace App\Jobs;

use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell a club's officers that a student asked to join, since accepting applicants is
 * their step (POLICY.md §Registration): officers accept, then the adviser approves.
 * Course members of an academic club are not applicants and never come through here.
 *
 * A club with no officers has nobody for that step, so its adviser is told instead —
 * the adviser can approve the application directly.
 *
 * Dispatched with dispatchSync: telling a handful of people must not wait on a queue
 * worker, which not every deployment runs.
 */
class NotifyOfficersOfMembershipRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Only a Join pressed by hand gets here: an academic club's course members join at
    // once, with nothing to accept (AcademicClubEnrollment).
    public function __construct(private int $clubMemberId) {}

    public function handle(): void
    {
        $member = ClubMember::with('club', 'user')->find($this->clubMemberId);

        if (! $member || ! $member->club || ! $member->user) {
            return;
        }

        $club = $member->club;
        $name = $member->user->name;

        $recipientIds = ClubOfficer::where('club_id', $club->id)
            ->where('user_id', '!=', $member->user_id)
            ->pluck('user_id')
            ->unique();

        $toAdviser = $recipientIds->isEmpty();

        if ($toAdviser) {
            $recipientIds = collect(array_filter([$club->adviser_id]));
        }

        $body = "{$name} asked to join {$club->name}."
            . ($toAdviser
                ? ' The club has no officers yet, so you can approve it directly.'
                : ' Review the request and submit it to your adviser.');

        foreach ($recipientIds as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'New membership request',
                'body'         => $body,
                'action_url'   => route('clubs.members.index'),
            ]);
        }
    }
}

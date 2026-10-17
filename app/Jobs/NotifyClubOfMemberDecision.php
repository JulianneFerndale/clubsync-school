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
 * The club adviser approved or rejected a membership application: tell the student,
 * and tell the officers who accepted them.
 *
 * The adviser who decided is not told about their own decision. Dispatched with
 * dispatchSync: telling a handful of people must not wait on a queue worker, which not
 * every deployment runs.
 */
class NotifyClubOfMemberDecision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $clubMemberId, private string $decision, private ?int $decidedBy = null) {}

    public function handle(): void
    {
        $member = ClubMember::with('club', 'user')->find($this->clubMemberId);

        if (! $member || ! $member->club) {
            return;
        }

        $club     = $member->club;
        $name     = $member->user?->name ?? 'A student';
        $approved = $this->decision === 'approved';
        $remarks  = $member->dsa_remarks ? ' Reason: ' . $member->dsa_remarks : '';

        // The student, in their own words.
        if ($member->user_id) {
            ClubNotification::create([
                'recipient_id' => $member->user_id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => $approved ? "Welcome to {$club->name}!" : 'Membership request not approved',
                'body'         => $approved
                    ? "The club adviser approved your membership. You can now see {$club->name}'s activities and announcements."
                    : "Your request to join {$club->name} was not approved by the club adviser.{$remarks}",
                'action_url'   => route('member.clubs.show', $club),
            ]);
        }

        // The officers (and an adviser who did not make the decision themselves).
        $recipientIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id');

        if ($club->adviser_id) {
            $recipientIds->push($club->adviser_id);
        }

        $recipientIds = $recipientIds->unique()
            ->reject(fn ($id) => (int) $id === (int) $member->user_id || (int) $id === (int) $this->decidedBy);

        foreach ($recipientIds as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => $approved ? 'New member approved' : 'Membership request rejected',
                'body'         => $approved
                    ? "{$name} is now a member of {$club->name}, approved by the club adviser."
                    : "{$name}'s request to join {$club->name} was rejected by the club adviser.{$remarks}",
                'action_url'   => route('clubs.members.index'),
            ]);
        }
    }
}

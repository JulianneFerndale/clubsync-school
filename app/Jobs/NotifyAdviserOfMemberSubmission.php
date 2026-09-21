<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notify a club's adviser that officers submitted member registrations for approval.
 * Member approval is the adviser's responsibility (not the DSA/admin).
 */
class NotifyAdviserOfMemberSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $clubId, private int $memberCount, private int $submittedBy) {}

    public function handle(): void
    {
        $club = Club::find($this->clubId);

        if (! $club || ! $club->adviser_id) {
            return; // no adviser assigned — nothing to notify (DSA can assign one)
        }

        $submitter = User::find($this->submittedBy);

        ClubNotification::create([
            'recipient_id' => $club->adviser_id,
            'sender_type'  => 'system',
            'club_id'      => $club->id,
            'title'        => 'Member registrations to review',
            'body'         => ($submitter?->name ?? 'A club officer') . " submitted {$this->memberCount} member(s) from {$club->name} for your approval.",
            'action_url'   => route('clubs.members.index'),
        ]);
    }
}

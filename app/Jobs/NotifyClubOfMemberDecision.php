<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyClubOfMemberDecision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $clubMemberId, private string $decision) {}

    public function handle(): void
    {
        $member = ClubMember::with('club', 'user')->find($this->clubMemberId);

        if (! $member || ! $member->club) {
            return;
        }

        $club = $member->club;

        $recipientIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id');

        if ($club->adviser_id) {
            $recipientIds->push($club->adviser_id);
        }

        $title = $this->decision === 'approved'
            ? 'Member registration approved'
            : 'Member registration rejected';

        $body = $this->decision === 'approved'
            ? ($member->user?->name ?? 'A submitted member') . " was approved by the DSA for {$club->name}."
            : ($member->user?->name ?? 'A submitted member') . " was rejected by the DSA for {$club->name}." . ($member->dsa_remarks ? ' Remarks: ' . $member->dsa_remarks : '');

        foreach ($recipientIds->unique() as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => $title,
                'body'         => $body,
                'action_url'   => route('clubs.members.index'),
            ]);
        }
    }
}

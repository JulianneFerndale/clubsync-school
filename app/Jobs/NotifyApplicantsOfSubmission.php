<?php

namespace App\Jobs;

use App\Models\ClubMember;
use App\Models\ClubNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The officers accepted these applicants and sent them on to the adviser: tell each
 * student, so they can follow their application through both steps rather than
 * hearing nothing between applying and the adviser's decision.
 *
 * Dispatched with dispatchSync, like the other membership notices: telling a handful
 * of people must not wait on a queue worker, which not every deployment runs.
 */
class NotifyApplicantsOfSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  array<int, int>  $clubMemberIds */
    public function __construct(private array $clubMemberIds) {}

    public function handle(): void
    {
        $applications = ClubMember::with('club')->whereIn('id', $this->clubMemberIds)->get();

        foreach ($applications as $application) {
            if (! $application->club || ! $application->user_id) {
                continue;
            }

            $club = $application->club;

            ClubNotification::create([
                'recipient_id' => $application->user_id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => "{$club->name} officers accepted your application",
                'body'         => $club->adviser_id
                    ? "Your application is now with the club adviser for final approval."
                    : "Your application is waiting for a club adviser to be assigned, who gives final approval.",
                'action_url'   => route('member.clubs.show', $club),
            ]);
        }
    }
}

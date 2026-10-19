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
 * Tell the Vice President for Administration that a club's room booking letter is waiting
 * for them (POLICY.md §Event Creation): the last approval an activity needs, after its
 * adviser's.
 *
 * Every VPA account is told, since the office's account may be run by the SSG. With no
 * VPA account at all the letter would wait on nobody, so the DSA is told instead —
 * the same fallback a club with no adviser gets. The letter still waits.
 */
class NotifyVpaOfBookingLetter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $activityId) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity?->club || ! $activity->isAwaitingVpa()) {
            return;
        }

        $club = $activity->club;
        $vpas = User::where('role', 'vpa')->where('is_suspended', false)->get();

        if ($vpas->isNotEmpty()) {
            foreach ($vpas as $vpa) {
                ClubNotification::create([
                    'recipient_id' => $vpa->id,
                    'sender_type'  => 'system',
                    'club_id'      => $club->id,
                    'title'        => 'Room booking letter to approve',
                    'body'         => "{$club->name} asks to use {$activity->venue} for \"{$activity->title}\" on {$activity->date->format('M j, Y')}. The club adviser has approved it.",
                    'action_url'   => route('vpa.letters.show', $activity),
                ]);
            }

            return;
        }

        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Letter waiting — no VPA account',
                'body'         => "{$club->name} sent a room booking letter for \"{$activity->title}\", but there is no Vice President for Administration account to approve it.",
                'action_url'   => route('dsa.activities.show', $activity),
            ]);
        }
    }
}

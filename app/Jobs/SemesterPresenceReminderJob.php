<?php

namespace App\Jobs;

use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Semester;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SemesterPresenceReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $semester = Semester::current()->whereDate('start_date', today())->first();

        if (! $semester) {
            return;
        }

        foreach (ClubOfficer::with('club')->get() as $officer) {
            ClubNotification::create([
                'recipient_id' => $officer->user_id,
                'sender_type'  => 'system',
                'club_id'      => $officer->club_id,
                'title'        => 'Semestral presence update due',
                'body'         => "The {$semester->label} has started. Please complete the semestral presence update for your members.",
                'action_url'   => route('clubs.presence.index'),
            ]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyDsaOfPresenceSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $clubId, private int $semesterId, private int $submittedBy) {}

    public function handle(): void
    {
        $club     = Club::find($this->clubId);
        $semester = Semester::find($this->semesterId);

        if (! $club || ! $semester) {
            return;
        }

        $submitter = User::find($this->submittedBy);

        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'Semestral presence update submitted',
                'body'         => ($submitter?->name ?? 'A club officer') . " completed the semestral presence update for {$club->name} ({$semester->label}).",
            ]);
        }
    }
}

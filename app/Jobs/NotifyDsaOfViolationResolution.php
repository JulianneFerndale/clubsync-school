<?php

namespace App\Jobs;

use App\Models\AiNotificationQueue;
use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyDsaOfViolationResolution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $violationId) {}

    public function handle(): void
    {
        $violation = AiNotificationQueue::with('club')->find($this->violationId);

        if (! $violation || ! $violation->club) {
            return;
        }

        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => $violation->club_id,
                'title'        => 'Compliance violation resolved',
                'body'         => "{$violation->club->name} marked \"{$violation->gap_label}\" as resolved." . ($violation->resolution_note ? ' Note: ' . $violation->resolution_note : ''),
                'action_url'   => route('dsa.violations.show', $violation),
            ]);
        }
    }
}

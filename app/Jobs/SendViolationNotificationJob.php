<?php

namespace App\Jobs;

use App\Models\AiNotificationQueue;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendViolationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $violationId) {}

    public function handle(FirebaseService $firebase): void
    {
        $violation = AiNotificationQueue::with('club')->find($this->violationId);

        if (! $violation || ! $violation->club) {
            return;
        }

        $club    = $violation->club;
        $content = $violation->finalContent() ?? 'AI Unavailable — Manual Draft Required.';

        $recipientIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id');

        if ($club->adviser_id) {
            $recipientIds->push($club->adviser_id);
        }

        foreach ($recipientIds->unique() as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'ai_queue',
                'club_id'      => $club->id,
                'title'        => 'Compliance Notice from the DSA',
                'body'         => $content,
                'action_url'   => route('clubs.violations.index'),
            ]);
        }

        try {
            $firebase->writeViolation($club->id, $violation->id, [
                'gap_type'  => $violation->gap_type,
                'status'    => 'sent',
                'sent_at'   => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Firebase violation mirror failed for violation #' . $violation->id . ': ' . $e->getMessage());
        }

        $violation->update([
            'status'  => 'edited_sent',
            'sent_at' => now(),
        ]);
    }
}

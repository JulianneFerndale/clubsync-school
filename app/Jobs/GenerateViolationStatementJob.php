<?php

namespace App\Jobs;

use App\Models\AiNotificationQueue;
use App\Models\AuditLog;
use App\Models\ClubNotification;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateViolationStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $violationId) {}

    public function handle(GeminiService $gemini): void
    {
        $violation = AiNotificationQueue::with('club')->find($this->violationId);

        if (! $violation || ! $violation->club) {
            return;
        }

        $statement = $gemini->generateViolationStatement($violation, $violation->club);

        $violation->update([
            'draft_content' => $statement,
            'ai_available'  => (bool) $statement,
        ]);

        AuditLog::record('ai.violation_statement.generated', $violation, [
            'club_id'      => $violation->club_id,
            'gap_type'     => $violation->gap_type,
            'ai_available' => (bool) $statement,
        ]);

        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'ai_queue',
                'club_id'      => $violation->club_id,
                // Name the exact requirement that was missed, and carry the specifics
                // (club, activity titles, dates) the scan already worked out — a raw
                // gap_type slug tells the DSA nothing actionable.
                'title'        => 'Compliance violation: ' . $violation->gap_label,
                'body'         => $violation->description . ' '
                    . ($statement ? 'An AI-drafted statement is ready for your review.' : 'AI Unavailable — manual draft required.'),
                'action_url'   => route('dsa.violations.show', $violation),
            ]);
        }
    }
}

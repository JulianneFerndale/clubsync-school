<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ClubActivity;
use App\Models\ClubNarrative;
use App\Models\ClubNotification;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateClubNarrativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $activityId) {}

    public function handle(GeminiService $gemini): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity || ! $activity->club) {
            return;
        }

        // Avoid duplicate narratives if completion is triggered more than once.
        if (ClubNarrative::where('club_activity_id', $activity->id)->exists()) {
            return;
        }

        $draft = $gemini->draftClubNarrative($activity, $activity->club);

        $narrative = ClubNarrative::create([
            'club_id'          => $activity->club_id,
            'club_activity_id' => $activity->id,
            'title'            => $activity->title,
            'draft_content'    => $draft,
            'ai_available'     => (bool) $draft,
            'status'           => 'pending_review',
        ]);

        AuditLog::record('ai.narrative.generated', $narrative, [
            'club_id'          => $activity->club_id,
            'club_activity_id' => $activity->id,
            'ai_available'     => (bool) $draft,
        ]);

        // The narrative is a DRAFT only. Per POLICY.md (AI & Automation), it cannot be
        // published automatically — the club adviser must review it first.
        if ($activity->club->adviser_id) {
            ClubNotification::create([
                'recipient_id' => $activity->club->adviser_id,
                'sender_type'  => 'ai_queue',
                'club_id'      => $activity->club_id,
                'title'        => 'AI narrative ready for review',
                'body'         => "{$activity->club->name}: a narrative for \"{$activity->title}\" "
                    . ($draft
                        ? 'has been drafted and is awaiting your review before it appears on the bulletin.'
                        : 'could not be drafted (AI Unavailable — Manual Draft Required). Please write one manually.'),
                'action_url'   => route('adviser.narratives.show', $narrative),
            ]);
        }
    }
}

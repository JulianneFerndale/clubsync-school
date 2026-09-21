<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyOfficersOfActivityDecision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $activityId, private string $decision) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club')->find($this->activityId);

        if (! $activity || ! $activity->club) {
            return;
        }

        $club = $activity->club;

        $recipientIds = ClubOfficer::where('club_id', $club->id)->pluck('user_id');

        if ($club->adviser_id) {
            $recipientIds->push($club->adviser_id);
        }

        $title = $this->decision === 'approved' ? 'Activity approved' : 'Activity rejected';

        $body = $this->decision === 'approved'
            ? "\"{$activity->title}\" was approved by the club adviser."
            : "\"{$activity->title}\" was rejected by the club adviser." . ($activity->review_remarks ? ' Remarks: ' . $activity->review_remarks : '');

        foreach ($recipientIds->unique() as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => $title,
                'body'         => $body,
                'action_url'   => route('officer.activities.show', $activity),
            ]);
        }
    }
}

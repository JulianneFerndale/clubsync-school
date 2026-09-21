<?php

namespace App\Jobs;

use App\Models\AiNotificationQueue;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubOfficerRecord;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DetectClubViolationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $clubs = Club::active()->get();

        foreach ($clubs as $club) {
            $this->checkOverduePresenceUpdate($club);
            $this->checkMissingChedReport($club);
            $this->checkOverdueActivityRequest($club);
            $this->checkUnupdatedOfficerInfo($club);
        }
    }

    private function alreadyOpen(Club $club, string $gapType): bool
    {
        return AiNotificationQueue::where('club_id', $club->id)
            ->where('gap_type', $gapType)
            ->where('is_resolved', false)
            ->exists();
    }

    private function raise(Club $club, string $gapType, string $description): void
    {
        if ($this->alreadyOpen($club, $gapType)) {
            return;
        }

        $violation = AiNotificationQueue::create([
            'club_id'     => $club->id,
            'gap_type'    => $gapType,
            'description' => $description,
            'status'      => 'pending_review',
            'ai_available' => true,
        ]);

        GenerateViolationStatementJob::dispatch($violation->id);
    }

    private function checkOverduePresenceUpdate(Club $club): void
    {
        $semester = Semester::current()->first();

        if (! $semester || today()->lt($semester->start_date->copy()->addDays(14))) {
            return;
        }

        $memberIds = $club->members()->where('registration_status', 'approved')->pluck('id');

        if ($memberIds->isEmpty()) {
            return;
        }

        $hasAnyStatus = \App\Models\MemberSemesterStatus::whereIn('club_member_id', $memberIds)
            ->where('semester_id', $semester->id)
            ->exists();

        if (! $hasAnyStatus) {
            $this->raise($club, 'overdue_presence_update', "{$club->name} has not submitted any semestral presence update for {$semester->label}, which started on {$semester->start_date->format('M j, Y')}.");
        }
    }

    private function checkMissingChedReport(Club $club): void
    {
        $overdueActivities = ClubActivity::where('club_id', $club->id)
            ->whereIn('activity_type', ['acle', 'community_involvement'])
            ->where('status', 'completed')
            ->where('completed_at', '<=', now()->subDays(7))
            ->whereDoesntHave('chedReport', fn ($q) => $q->where('is_finalized', true))
            ->get();

        if ($overdueActivities->isNotEmpty()) {
            $titles = $overdueActivities->pluck('title')->implode(', ');
            $this->raise($club, 'missing_ched_report', "{$club->name} has completed activities pending a finalized CHED report for more than 7 days: {$titles}.");
        }
    }

    private function checkOverdueActivityRequest(Club $club): void
    {
        $overdue = ClubActivity::where('club_id', $club->id)
            ->where('approval_status', 'pending_approval')
            ->where('created_at', '<=', now()->subDays(5))
            ->get();

        if ($overdue->isNotEmpty()) {
            $titles = $overdue->pluck('title')->implode(', ');
            $this->raise($club, 'overdue_activity_request', "{$club->name} has activity approval requests pending for more than 5 days without follow-up: {$titles}.");
        }
    }

    private function checkUnupdatedOfficerInfo(Club $club): void
    {
        $october1 = Carbon::create(now()->year, 10, 1);

        if (today()->lt($october1)) {
            return;
        }

        $updatedSince = ClubOfficerRecord::where('club_id', $club->id)
            ->where('updated_at', '>=', $october1)
            ->exists();

        if (! $updatedSince) {
            $this->raise($club, 'unupdated_officer_info', "{$club->name} has not updated its officer records since October 1, {$october1->year}.");
        }
    }
}

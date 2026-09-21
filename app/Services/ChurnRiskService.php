<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * Computes a churn-risk profile for each active member of a club.
 *
 * Risk is a 0–100 score (higher = more at risk) built from four explainable
 * signals, weighted to sum to 100:
 *   - Attendance      (40): share of the club's completed activities the member missed
 *   - Semester presence (35): officer-marked status for the current semester
 *   - Unpaid fees     (15): has outstanding club fees
 *   - Inactivity      (10): never attended despite activities having taken place
 *
 * IMPORTANT (POLICY.md): this data must never be exposed to student members or
 * advisers. Access control is the caller's responsibility (route middleware +
 * controller scoping); this service only computes.
 *
 * All signals are gathered with a fixed, small number of queries per club
 * (no per-member queries) to keep the engine cheap on large clubs.
 */
class ChurnRiskService
{
    public const HIGH = 67;
    public const MEDIUM = 34;

    /**
     * @return Collection<int, array> One row per active member, sorted most-at-risk first.
     */
    public function forClub(Club $club, ?Semester $semester = null): Collection
    {
        $semester ??= Semester::current()->first();

        $members = ClubMember::where('club_id', $club->id)
            ->where('status', 'active')
            ->with('user:id,first_name,last_name,email,edp_number')
            ->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $userIds   = $members->pluck('user_id')->all();
        $memberIds = $members->pluck('id')->all();

        // ── Completed activities for this club (scoped to the semester if known) ──
        $activityQuery = ClubActivity::where('club_id', $club->id)->where('status', 'completed');
        if ($semester) {
            $activityQuery->whereBetween('date', [$semester->start_date, $semester->end_date]);
        }
        $activityIds   = $activityQuery->pluck('id')->all();
        $totalActivities = count($activityIds);

        // Attendance counts per member for those activities (a single grouped query).
        $attendedByUser = $activityIds
            ? Attendance::whereIn('event_id', $activityIds)
                ->whereIn('user_id', $userIds)
                ->whereNotNull('time_in')
                ->selectRaw('user_id, COUNT(DISTINCT event_id) AS c')
                ->groupBy('user_id')
                ->pluck('c', 'user_id')
            : collect();

        // ── Outstanding fees per member ──
        $feeIds = Fee::where('club_id', $club->id)->pluck('id')->all();
        $unpaidByUser = $feeIds
            ? FeePayment::whereIn('fee_id', $feeIds)
                ->whereIn('user_id', $userIds)
                ->where('status', 'pending')
                ->selectRaw('user_id, COUNT(*) AS c')
                ->groupBy('user_id')
                ->pluck('c', 'user_id')
            : collect();

        // ── Current-semester presence status per membership ──
        $presenceByMember = $semester
            ? MemberSemesterStatus::where('semester_id', $semester->id)
                ->whereIn('club_member_id', $memberIds)
                ->pluck('semester_status', 'club_member_id')
            : collect();

        return $members
            ->map(fn (ClubMember $member) => $this->profile(
                $member,
                $totalActivities,
                (int) ($attendedByUser[$member->user_id] ?? 0),
                (int) ($unpaidByUser[$member->user_id] ?? 0),
                $presenceByMember[$member->id] ?? null,
            ))
            ->sortByDesc('score')
            ->values();
    }

    private function profile(ClubMember $member, int $totalActivities, int $attended, int $unpaid, ?string $presence): array
    {
        $factors = [];
        $score = 0;

        // Attendance (0–40)
        if ($totalActivities > 0) {
            $missed = max(0, $totalActivities - $attended);
            $missedRate = $missed / $totalActivities;
            $score += (int) round($missedRate * 40);
            if ($missed > 0) {
                $factors[] = "Missed {$missed} of {$totalActivities} activities";
            }
        }

        // Semester presence (0–35)
        $score += match ($presence) {
            'dropped'  => 35,
            'inactive' => 25,
            'active'   => 0,
            default    => 12, // not yet assessed this semester
        };
        if (in_array($presence, ['dropped', 'inactive'], true)) {
            $factors[] = 'Marked ' . $presence . ' this semester';
        } elseif ($presence === null) {
            $factors[] = 'No semester status recorded';
        }

        // Unpaid fees (0–15)
        if ($unpaid > 0) {
            $score += 15;
            $factors[] = "{$unpaid} unpaid fee" . ($unpaid > 1 ? 's' : '');
        }

        // Inactivity (0–10): activities happened but the member never showed up
        if ($totalActivities > 0 && $attended === 0) {
            $score += 10;
            $factors[] = 'No attendance recorded';
        }

        $score = min(100, $score);

        return [
            'member'           => $member,
            'user'             => $member->user,
            'score'            => $score,
            'tag'              => $this->tag($score),
            'attended'         => $attended,
            'total_activities' => $totalActivities,
            'unpaid_fees'      => $unpaid,
            'presence'         => $presence,
            'factors'          => $factors ?: ['Actively engaged'],
        ];
    }

    private function tag(int $score): string
    {
        return match (true) {
            $score >= self::HIGH   => 'high',
            $score >= self::MEDIUM => 'medium',
            default                => 'low',
        };
    }

    /**
     * Roll a member risk collection into {high, medium, low, total} counts.
     */
    public function summarize(Collection $rows): array
    {
        return [
            'total'  => $rows->count(),
            'high'   => $rows->where('tag', 'high')->count(),
            'medium' => $rows->where('tag', 'medium')->count(),
            'low'    => $rows->where('tag', 'low')->count(),
        ];
    }
}

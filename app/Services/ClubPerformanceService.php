<?php

namespace App\Services;

use App\Models\AiNotificationQueue;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubReportSubmission;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * How well each club is doing over a semester, for the DSA.
 *
 * This replaced the Churn Risk Engine on the DSA side. The churn engine scores
 * individual members; the DSA's question is about clubs, so everything here is a
 * club-level figure. There are no per-member scores and no risk tags. Churn Risk is
 * still officer-only (POLICY.md §Role-Based Access).
 *
 * The score runs from 0 to 100 and is built from five signals. Each is a share from
 * 0 to 1, weighted:
 *   - Activities held  (30): completed activities against one a month so far
 *   - Turnout          (25): average share of active members at those activities
 *   - Presence update  (15): members given a semester status (after the same 14-day
 *                            grace DetectClubViolationsJob allows)
 *   - Fee collection   (15): payments settled, for fees already due
 *   - Semester reports (15): report types filed, counted from 30 days before the end
 * A signal that does not apply yet (no fee due, not report season) is left out and
 * the rest are re-weighted, so no club is marked down for something it could not
 * have done. Each compliance notice the DSA sent that the club has not resolved
 * takes 5 points off.
 *
 * The whole list takes a fixed number of grouped queries, not a set per club,
 * because the database is remote and there are about 40 clubs.
 */
class ClubPerformanceService
{
    public const WEIGHTS = [
        'activities' => 30,
        'turnout'    => 25,
        'presence'   => 15,
        'fees'       => 15,
        'reports'    => 15,
    ];

    /** Points off for each compliance notice that has been sent and not resolved. */
    public const NOTICE_PENALTY = 5;

    /** Matches DetectClubViolationsJob: presence is not expected in the first two weeks. */
    public const PRESENCE_GRACE_DAYS = 14;

    /** Semester-end reports count from this many days before the semester ends. */
    public const REPORT_SEASON_DAYS = 30;

    public const STRONG = 75;
    public const FAIR   = 50;

    public const STANDINGS = [
        'strong'      => 'Performing well',
        'fair'        => 'Fair',
        'weak'        => 'Needs support',
        'inactive'    => 'No activities held',
        'not_started' => 'Semester not started',
    ];

    /**
     * One row per club, best-performing first.
     *
     * @param  Collection<int, Club>  $clubs
     * @return Collection<int, array>
     */
    public function forClubs(Collection $clubs, Semester $semester): Collection
    {
        if ($clubs->isEmpty()) {
            return collect();
        }

        $clubIds = $clubs->pluck('id')->all();
        $window  = $this->window($semester);

        $members = ClubMember::whereIn('club_id', $clubIds)
            ->selectRaw('club_id, status, count(*) as c')
            ->groupBy('club_id', 'status')
            ->get()
            ->groupBy('club_id');

        $activities = ClubActivity::whereIn('club_id', $clubIds)
            ->whereBetween('date', [$semester->start_date->toDateString(), $semester->end_date->toDateString()])
            ->orderBy('date')
            ->get(['id', 'club_id', 'title', 'date', 'time_start', 'status', 'approval_status', 'activity_type']);

        $heldIds   = $activities->where('status', 'completed')->pluck('id')->all();
        $attendees = $heldIds
            ? Attendance::whereIn('event_id', $heldIds)
                ->whereNotNull('time_in')
                ->selectRaw('event_id, count(distinct user_id) as c')
                ->groupBy('event_id')
                ->pluck('c', 'event_id')
            : collect();

        $presence = MemberSemesterStatus::where('semester_id', $semester->id)
            ->join('club_members', 'club_members.id', '=', 'member_semester_statuses.club_member_id')
            ->whereIn('club_members.club_id', $clubIds)
            ->where('club_members.status', 'active')
            ->selectRaw('club_members.club_id, count(distinct club_members.id) as c')
            ->groupBy('club_members.club_id')
            ->pluck('c', 'club_id');

        $newMembers = ClubMember::whereIn('club_id', $clubIds)
            ->where('status', 'active')
            ->whereBetween('approved_at', [$window['start'], $window['as_of']->copy()->endOfDay()])
            ->selectRaw('club_id, count(*) as c')
            ->groupBy('club_id')
            ->pluck('c', 'club_id');

        // Only fees already due count: a fee due next month is still being collected.
        $fees = FeePayment::join('fees', 'fees.id', '=', 'fee_payments.fee_id')
            ->whereIn('fees.club_id', $clubIds)
            ->where('fees.status', '!=', Fee::CANCELLED) // no longer collected, so not a shortfall
            ->whereBetween('fees.due_date', [$window['start']->toDateString(), $window['as_of']->toDateString()])
            ->selectRaw('fees.club_id, fee_payments.status, count(*) as c')
            ->groupBy('fees.club_id', 'fee_payments.status')
            ->get()
            ->groupBy('club_id');

        $announcements = Announcement::whereIn('club_id', $clubIds)
            ->where('status', 'published')
            ->whereBetween('published_at', [$window['start'], $semester->end_date->copy()->endOfDay()])
            ->selectRaw('club_id, count(*) as c')
            ->groupBy('club_id')
            ->pluck('c', 'club_id');

        // A report returned for revision has not been filed yet.
        $reports = ClubReportSubmission::whereIn('club_id', $clubIds)
            ->where('semester_id', $semester->id)
            ->where('status', '!=', 'returned')
            ->get(['club_id', 'report_type', 'status'])
            ->groupBy('club_id');

        $notices = AiNotificationQueue::whereIn('club_id', $clubIds)
            ->where('status', 'edited_sent')
            ->where('is_resolved', false)
            ->selectRaw('club_id, count(*) as c')
            ->groupBy('club_id')
            ->pluck('c', 'club_id');

        $activitiesByClub = $activities->groupBy('club_id');

        return $clubs
            ->map(function (Club $club) use ($semester, $window, $members, $activitiesByClub, $attendees, $presence, $newMembers, $fees, $announcements, $reports, $notices) {
                $counts = collect($members->get($club->id, []))->pluck('c', 'status');
                $clubReports = collect($reports->get($club->id, []));
                $clubFees = collect($fees->get($club->id, []))->pluck('c', 'status');

                return $this->profile($club, $semester, $window, [
                    'members'       => (int) ($counts['active'] ?? 0),
                    'applicants'    => (int) ($counts['pending'] ?? 0),
                    'new_members'   => (int) ($newMembers[$club->id] ?? 0),
                    'activities'    => collect($activitiesByClub->get($club->id, [])),
                    'attendees'     => $attendees,
                    'presence'      => (int) ($presence[$club->id] ?? 0),
                    'fees_paid'     => (int) ($clubFees['paid'] ?? 0),
                    'fees_total'    => (int) $clubFees->sum(),
                    'announcements' => (int) ($announcements[$club->id] ?? 0),
                    'reports_filed' => $clubReports->pluck('report_type')->unique()->count(),
                    'reports_accepted' => $clubReports->where('status', 'accepted')->pluck('report_type')->unique()->count(),
                    'open_notices'  => (int) ($notices[$club->id] ?? 0),
                ]);
            })
            ->sort(fn (array $a, array $b) => [$b['score'] ?? -1, $a['club']->name] <=> [$a['score'] ?? -1, $b['club']->name])
            ->values();
    }

    /** The same row for one club. */
    public function forClub(Club $club, Semester $semester): array
    {
        return $this->forClubs(collect([$club]), $semester)->first();
    }

    /**
     * How many clubs sit in each standing, plus the semester-wide figures.
     *
     * @param  Collection<int, array>  $rows
     */
    public function summarize(Collection $rows): array
    {
        $turnouts = $rows->pluck('turnout')->filter(fn ($t) => $t !== null);

        return [
            'clubs'     => $rows->count(),
            'standings' => collect(array_keys(self::STANDINGS))
                ->mapWithKeys(fn ($s) => [$s => $rows->where('standing', $s)->count()])
                ->all(),
            'held'      => $rows->sum('held'),
            'turnout'   => $turnouts->isEmpty() ? null : $turnouts->avg(),
            'notices'   => $rows->sum('open_notices'),
        ];
    }

    /**
     * The months a semester spans, each marked as elapsed or still ahead.
     *
     * @return array<string, array{label: string, future: bool}> keyed Y-m
     */
    public function months(Semester $semester): array
    {
        $asOf   = $this->window($semester)['as_of'];
        $months = [];

        for ($m = $semester->start_date->copy()->startOfMonth(); $m->lte($semester->end_date); $m->addMonth()) {
            $months[$m->format('Y-m')] = [
                'label'  => $m->format('M'),
                'future' => $m->gt($asOf),
            ];
        }

        return $months;
    }

    /** Where "so far" ends: today, or the semester's last day once it is over. */
    private function window(Semester $semester): array
    {
        $today = today();

        return [
            'start'   => $semester->start_date->copy()->startOfDay(),
            'as_of'   => $today->gt($semester->end_date) ? $semester->end_date->copy()->startOfDay() : $today,
            'started' => $today->gte($semester->start_date),
            'ended'   => $today->gt($semester->end_date),
        ];
    }

    private function profile(Club $club, Semester $semester, array $window, array $d): array
    {
        /** @var Collection<int, ClubActivity> $activities */
        $activities = $d['activities'];
        $held       = $activities->where('status', 'completed');
        $members    = $d['members'];

        // Turnout per held activity: distinct attendees over the club's active
        // members, capped at 100% (officers and guests can push it over).
        $turnoutOf = fn (ClubActivity $a) => $members > 0
            ? min(1, ($d['attendees'][$a->id] ?? 0) / $members)
            : null;

        $heldTurnouts = $held->map($turnoutOf)->filter(fn ($t) => $t !== null);
        $turnout      = $heldTurnouts->isEmpty() ? null : $heldTurnouts->avg();

        $monthly = [];
        foreach ($this->months($semester) as $key => $month) {
            $inMonth     = $held->filter(fn (ClubActivity $a) => $a->date->format('Y-m') === $key);
            $monthTurnout = $inMonth->map($turnoutOf)->filter(fn ($t) => $t !== null);

            $monthly[$key] = $month + [
                'held'    => $inMonth->count(),
                'turnout' => $monthTurnout->isEmpty() ? null : $monthTurnout->avg(),
            ];
        }

        $daysSoFar = $window['started'] ? (int) $window['start']->diffInDays($window['as_of']) + 1 : 0;
        $expected  = max(1, (int) ceil($daysSoFar / 30));

        $components = [
            'activities' => [
                'applies' => true,
                'value'   => min(1, $held->count() / $expected),
                'detail'  => "{$held->count()} held, " . $expected . ' expected so far (one a month)',
            ],
            'turnout' => [
                'applies' => true,
                // A club that held nothing had nobody turn up.
                'value'   => $turnout ?? 0.0,
                'detail'  => $turnout === null
                    ? ($held->isEmpty() ? 'No activities held yet' : 'No active members to measure against')
                    : round($turnout * 100) . '% of active members on average',
            ],
            'presence' => [
                'applies' => $members > 0
                    && $window['as_of']->gte($semester->start_date->copy()->addDays(self::PRESENCE_GRACE_DAYS)),
                'value'   => $members > 0 ? min(1, $d['presence'] / $members) : 0.0,
                'detail'  => "{$d['presence']} of {$members} active members given a status this semester",
            ],
            'fees' => [
                'applies' => $d['fees_total'] > 0,
                'value'   => $d['fees_total'] > 0 ? $d['fees_paid'] / $d['fees_total'] : 0.0,
                'detail'  => $d['fees_total'] > 0
                    ? "{$d['fees_paid']} of {$d['fees_total']} payments settled for fees already due"
                    : 'No fee has fallen due this semester',
            ],
            'reports' => [
                'applies' => $window['as_of']->gte($semester->end_date->copy()->subDays(self::REPORT_SEASON_DAYS)),
                'value'   => $d['reports_filed'] / count(ClubReportSubmission::TYPES),
                'detail'  => "{$d['reports_filed']} of " . count(ClubReportSubmission::TYPES) . ' semester reports filed'
                    . ($d['reports_accepted'] ? ", {$d['reports_accepted']} accepted" : ''),
            ],
        ];

        foreach ($components as $key => &$component) {
            $component['weight'] = self::WEIGHTS[$key];
        }
        unset($component);

        $score    = null;
        $standing = 'not_started';

        if ($window['started']) {
            $applicable = collect($components)->where('applies', true);
            $weighted   = $applicable->sum(fn ($c) => $c['value'] * $c['weight']) / $applicable->sum('weight');
            $score      = max(0, min(100, (int) round($weighted * 100) - $d['open_notices'] * self::NOTICE_PENALTY));

            $standing = match (true) {
                $held->isEmpty()      => 'inactive',
                $score >= self::STRONG => 'strong',
                $score >= self::FAIR   => 'fair',
                default                => 'weak',
            };
        }

        return [
            'club'             => $club,
            'score'            => $score,
            'standing'         => $standing,
            'components'       => $components,
            'held'             => $held->count(),
            'upcoming'         => $activities->where('status', 'scheduled')
                ->whereIn('approval_status', ClubActivity::CONFIRMED_APPROVAL_STATUSES)
                ->filter(fn (ClubActivity $a) => $a->date->gte(today()))
                ->count(),
            'pending'          => $activities->whereIn('approval_status', ClubActivity::AWAITING_APPROVAL_STATUSES)->count(),
            'rejected'         => $activities->where('approval_status', 'rejected')->count(),
            'turnout'          => $turnout,
            'members'          => $members,
            'applicants'       => $d['applicants'],
            'new_members'      => $d['new_members'],
            'announcements'    => $d['announcements'],
            'reports_filed'    => $d['reports_filed'],
            'reports_accepted' => $d['reports_accepted'],
            'open_notices'     => $d['open_notices'],
            'monthly'          => $monthly,
            'activities'       => $activities->map(fn (ClubActivity $a) => [
                'activity'  => $a,
                'attendees' => $a->status === 'completed' ? (int) ($d['attendees'][$a->id] ?? 0) : null,
                'turnout'   => $a->status === 'completed' ? $turnoutOf($a) : null,
            ])->values(),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClubActivity extends Model
{
    /**
     * The SCC-configured venue list: rooms on the main campus only. POLICY.md
     * § Event Creation requires venues to be chosen from this list rather than
     * entered as free text. Every room is exclusive — findVenueConflict() blocks
     * a second booking of the same room for an overlapping time.
     */
    public const VENUES = [
        'AVR',
        'EB 2-1', 'EB 2-2', 'EB 2-3', 'EB 2-4', 'EB 2-5',
        'EB 3-1', 'EB 3-2', 'EB 3-3', 'EB 3-4', 'EB 3-5',
        'EB 4-5',
        'Moot Court',
        'JD 1', 'JD 2',
        'PC 1-1', 'PC 1-2', 'PC 1-3', 'PC 1-4',
        'PC 2-1', 'PC 2-2', 'PC 2-3', 'PC 2-4', 'PC 2-5', 'PC 2-6',
        'PC 3-1',
        'PC Lab A', 'PC Lab B', 'PC Lab C',
        'SN 1-1', 'SN 1-2', 'SN 1-3',
        'SN 2-2', 'SN 2-3',
        'SN 3-1', 'SN 3-2',
        'ST 1-1', 'ST 1-2',
        'Library',
        'YDC',
    ];

    /**
     * Venues that are not exclusive, so two activities naming one do not clash.
     * "Others" was the catch-all on the old venue list and is no longer offered;
     * it stays here so activities booked under it before the switch to main-campus
     * rooms never block each other when they are edited.
     */
    public const NON_EXCLUSIVE_VENUES = ['Others'];

    /** Human-readable names for the activity_type values the officer form offers. */
    public const TYPE_LABELS = [
        'internal_meeting'      => 'Internal Meeting',
        'acle'                  => 'ACLE (Alternative Classroom Learning Experience)',
        'community_involvement' => 'Community Involvement',
        'campus_resource_use'   => 'Campus Resource Use',
        'other_external'        => 'Other External Activity',
    ];

    protected $fillable = [
        'club_id', 'title', 'description', 'date', 'time_start', 'time_end',
        'venue', 'purpose', 'expected_participants', 'budget_amount', 'status', 'completed_at', 'event_type',
        'created_by', 'post_report_content', 'post_report_status',
        'activity_type', 'target_year_levels', 'approval_status', 'review_remarks',
        'approval_letter_path', 'approved_at', 'approved_by',
        'letter_body', 'letter_ai_drafted', 'letter_signatures',
        'vpa_decision', 'vpa_reviewed_by', 'vpa_reviewed_at',
    ];

    /** approval_status values in which an activity counts as confirmed (POLICY.md § Event Creation). */
    public const CONFIRMED_APPROVAL_STATUSES = ['no_approval_needed', 'approved'];

    /**
     * Still waiting on someone's approval: the room booking letter is with the VPA, or
     * the VPA has approved it and it is with the club adviser.
     */
    public const AWAITING_APPROVAL_STATUSES = ['pending_vpa', 'pending_approval'];

    /** How each approval_status reads to the people following an activity. */
    public const APPROVAL_LABELS = [
        'no_approval_needed' => 'No Approval Needed',
        'pending_vpa'        => 'Awaiting VPA',
        'pending_approval'   => 'Awaiting Adviser',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
    ];

    protected $casts = [
        'date'                  => 'date',
        'expected_participants' => 'integer',
        'budget_amount'         => 'decimal:2',
        'approved_at'           => 'datetime',
        'completed_at'          => 'datetime',
        'target_year_levels'    => 'array',
        'letter_ai_drafted'     => 'boolean',
        'letter_signatures'     => 'array',
        'vpa_reviewed_at'       => 'datetime',
    ];

    /**
     * Comma-separated ordinal list of the year levels this activity targets,
     * e.g. "1st Year, 2nd Year". Falls back to "All year levels" when unset.
     */
    public function getTargetYearLevelsLabelAttribute(): string
    {
        $levels = $this->target_year_levels;

        if (empty($levels)) {
            return 'All year levels';
        }

        sort($levels);

        return collect($levels)->map(fn ($level) => year_level_label((int) $level))->implode(', ');
    }

    /**
     * Confirmed and still going ahead: an activity the VPA and the adviser approved (or,
     * from before that, an internal meeting scheduled outright) that has not been
     * cancelled. Only these reach members.
     */
    public function isConfirmed(): bool
    {
        return in_array($this->approval_status, self::CONFIRMED_APPROVAL_STATUSES, true)
            && $this->status !== 'cancelled';
    }

    /**
     * Whether this activity is aimed at a member in $yearLevel.
     *
     * An empty target list means the activity is open to everyone. A member with
     * no year level on record is included either way — dropping someone because
     * their profile is incomplete would silently lose them the activity.
     */
    public function reachesYearLevel(?int $yearLevel): bool
    {
        $levels = $this->target_year_levels;

        return empty($levels) || $yearLevel === null || in_array($yearLevel, $levels, true);
    }

    public function getActivityTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->activity_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->activity_type));
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** The VPA account that approved or returned the room booking letter. */
    public function vpaReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vpa_reviewed_by');
    }

    /**
     * Whether this activity carries a room booking letter: every activity added since the
     * VPA step began. Older ones went to the adviser alone, or (internal meetings) were
     * scheduled outright.
     */
    public function hasBookingLetter(): bool
    {
        return $this->letter_body !== null;
    }

    /** The room booking letter is with the VPA. */
    public function isAwaitingVpa(): bool
    {
        return $this->approval_status === 'pending_vpa';
    }

    /** The VPA, not the adviser, turned the letter down. */
    public function wasRejectedByVpa(): bool
    {
        return $this->approval_status === 'rejected' && $this->vpa_decision === 'rejected';
    }

    /**
     * One signer's entry on the letter: name, position, when they signed and the
     * signature they signed with (null when they had none on file).
     *
     * @param  'officer'|'vpa'|'adviser'  $role
     * @return array{user_id: int|null, name: string, position: string, signed_at: string, signature_path: string|null}|null
     */
    public function letterSignature(string $role): ?array
    {
        return $this->letter_signatures[$role] ?? null;
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return self::APPROVAL_LABELS[$this->approval_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->approval_status));
    }

    /** The query form of isConfirmed(): what members are told about and may see. */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('approval_status', self::CONFIRMED_APPROVAL_STATUSES)
            ->where('status', '!=', 'cancelled');
    }

    public function scopeNeedsApproval($query)
    {
        return $query->where('activity_type', '!=', 'internal_meeting');
    }

    public function attendance(): HasMany
    {
        // The attendance table's FK is still "event_id" (kept from before the
        // events → club_activities rename), so specify it explicitly; otherwise
        // Eloquent guesses "club_activity_id", which does not exist.
        return $this->hasMany(Attendance::class, 'event_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ActivityChangeLog::class)->orderByDesc('created_at');
    }

    /** Photos, receipts and properties the officers attached for the semester-end reports. */
    public function photos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class)->orderBy('created_at')->orderBy('id');
    }

    public function isMeeting(): bool
    {
        return $this->activity_type === 'internal_meeting';
    }

    /**
     * The narrative report already submitted to the DSA that covers this activity, if any.
     * Its photos and budget are then part of an approved document and stay as they are.
     */
    public function submittedReport(): ?SemesterNarrativeReport
    {
        return SemesterNarrativeReport::submitted()
            ->where('club_id', $this->club_id)
            ->get()
            ->first(fn (SemesterNarrativeReport $report) => in_array($this->id, $report->activityIds(), true));
    }

    /**
     * Activities that still hold $venue on $date.
     *
     * Cancelled and rejected activities release their slot; everything else
     * (scheduled, with the VPA or the adviser, approved, completed) keeps it.
     */
    public function scopeHoldingVenue(Builder $query, string $venue, string $date, ?int $ignoreId = null): Builder
    {
        return $query
            ->where('venue', $venue)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('approval_status', '!=', 'rejected')
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId));
    }

    /**
     * The first activity whose booking of $venue on $date overlaps
     * [$timeStart, $timeEnd), or null when the slot is free.
     *
     * The overlap is half-open, so back-to-back bookings (09:00–11:00 followed by
     * 11:00–13:00) do not collide. Comparison happens in PHP on minute values
     * rather than in SQL because the column is written as 'H:i' by the officer
     * form but normalised to 'H:i:s' by PostgreSQL — comparing those two shapes
     * as strings silently mis-handles the exact-boundary case. The candidate set
     * is one venue on one date, so this stays a single cheap query.
     *
     * @param  int|null  $ignoreId  The activity being edited, excluded from its own check.
     */
    public static function findVenueConflict(
        string $venue,
        string $date,
        string $timeStart,
        string $timeEnd,
        ?int $ignoreId = null,
    ): ?self {
        if (in_array($venue, self::NON_EXCLUSIVE_VENUES, true)) {
            return null;
        }

        $start = self::minutesFromTime($timeStart);
        $end   = self::minutesFromTime($timeEnd);

        return static::holdingVenue($venue, $date, $ignoreId)
            ->orderBy('time_start')
            ->get()
            ->first(fn (self $other) => $start < self::minutesFromTime($other->time_end)
                && $end > self::minutesFromTime($other->time_start));
    }

    /**
     * Venues from the SCC list that are free for the whole of [$timeStart, $timeEnd)
     * on $date, excluding $except (normally the one the officer just tried).
     *
     * @return list<string>
     */
    public static function venuesFreeAt(string $date, string $timeStart, string $timeEnd, ?string $except = null): array
    {
        return array_values(array_filter(
            self::VENUES,
            fn (string $venue) => $venue !== $except
                && ! in_array($venue, self::NON_EXCLUSIVE_VENUES, true)
                && ! self::findVenueConflict($venue, $date, $timeStart, $timeEnd),
        ));
    }

    /** Minutes since midnight for an 'H:i' or 'H:i:s' time string. */
    private static function minutesFromTime(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time, 3), 2, '0');

        return ((int) $hours * 60) + (int) $minutes;
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())->where('status', 'scheduled')->orderBy('date');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}

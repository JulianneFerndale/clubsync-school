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
     * The SCC-configured venue list. POLICY.md § Event Creation requires venues to
     * be chosen from this list rather than entered as free text.
     */
    public const VENUES = [
        'SCC Gymnasium',
        'Main AV Room',
        'Open Court',
        'Function Hall',
        'Covered Court',
        'Others',
    ];

    /**
     * Venues that are not exclusive, so two activities naming one do not clash.
     * "Others" is the catch-all on the venue list — two clubs picking it are not
     * necessarily in the same room, so it must not block a booking.
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
        'venue', 'purpose', 'expected_participants', 'status', 'completed_at', 'event_type',
        'created_by', 'post_report_content', 'post_report_status',
        'activity_type', 'target_year_levels', 'approval_status', 'review_remarks',
        'approval_letter_path', 'approved_at', 'approved_by',
    ];

    /** approval_status values in which an activity counts as confirmed (POLICY.md § Event Creation). */
    public const CONFIRMED_APPROVAL_STATUSES = ['no_approval_needed', 'approved'];

    protected $casts = [
        'date'                  => 'date',
        'expected_participants' => 'integer',
        'approved_at'           => 'datetime',
        'completed_at'          => 'datetime',
        'target_year_levels'    => 'array',
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
     * Confirmed and still going ahead: an internal meeting, or an activity the
     * DSA approved, that has not been cancelled. Only these reach members.
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

    public function chedReport(): HasOne
    {
        return $this->hasOne(ChedReport::class, 'club_activity_id');
    }

    /**
     * Activities that still hold $venue on $date.
     *
     * Cancelled and DSA-rejected activities release their slot; everything else
     * (scheduled, pending approval, approved, completed) keeps it.
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

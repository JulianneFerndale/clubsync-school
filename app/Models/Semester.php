<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    /** The three terms a school year runs through, in the order they happen. */
    public const TERMS = ['first', 'second', 'summer'];

    /** Summer closes the school year: the next one begins when it ends. */
    public const CLOSING_TERM = 'summer';

    protected $fillable = ['label', 'term', 'academic_year', 'start_date', 'end_date', 'is_current'];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function memberStatuses(): HasMany
    {
        return $this->hasMany(MemberSemesterStatus::class);
    }

    public function narrativeReports(): HasMany
    {
        return $this->hasMany(SemesterNarrativeReport::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    public function isSummer(): bool
    {
        return $this->term === self::CLOSING_TERM;
    }

    /**
     * The school year the system is in, e.g. "2026-2027".
     *
     * Read off the semester flagged current. Null when none is — a state the rest of
     * the system already treats as "do nothing", and this is no different: a turnover
     * must not be declared on a guess about what year it is.
     */
    public static function currentAcademicYear(): ?string
    {
        return static::current()->value('academic_year');
    }

    /**
     * The day a school year began — the start of its earliest semester.
     *
     * This is the line officer turnover is measured against: officers appointed on or
     * after it belong to this school year, and a club with none has not replaced its
     * officers yet.
     */
    public static function academicYearStart(string $academicYear): ?\Carbon\Carbon
    {
        $start = static::forAcademicYear($academicYear)->min('start_date');

        return $start ? \Carbon\Carbon::parse($start)->startOfDay() : null;
    }
}

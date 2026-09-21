<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'slug', 'acronym', 'adviser', 'club_type',
        'course_slug', 'department_slug', 'description',
        'is_active', 'name', 'profile_photo_url', 'banner_image',
        'type', 'college_id', 'adviser_id', 'facebook_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(ClubOfficer::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClubActivity::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function chedReports(): HasMany
    {
        return $this->hasMany(ChedReport::class);
    }

    /** A club is "compliant" once it has at least one finalized (submitted) report. */
    public function scopeCompliant($query)
    {
        return $query->whereHas('chedReports', fn ($q) => $q->where('is_finalized', true));
    }

    public function scopeNonCompliant($query)
    {
        return $query->whereDoesntHave('chedReports', fn ($q) => $q->where('is_finalized', true));
    }

    /** Clubs that have member registrations still awaiting the adviser's decision. */
    public function scopePendingApplications($query)
    {
        return $query->whereHas('members', fn ($q) => $q->where('registration_status', 'pending'));
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function adviserUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_slug', 'slug');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'club_course');
    }

    public function scopeAcademic($query)
    {
        return $query->where(fn ($q) => $q->where('type', 'academic')->orWhere('club_type', 'Academic'));
    }

    public function scopeNonAcademic($query)
    {
        return $query->where(fn ($q) => $q->where('type', 'non_academic')->orWhere('club_type', 'Non-Academic'));
    }

    /** In-memory twin of scopeAcademic(), for clubs already loaded. */
    public function isAcademicType(): bool
    {
        return $this->type === 'academic' || $this->club_type === 'Academic';
    }

    /** In-memory twin of scopeNonAcademic(), for clubs already loaded. */
    public function isNonAcademicType(): bool
    {
        return $this->type === 'non_academic' || $this->club_type === 'Non-Academic';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActiveMemberCountAttribute(): int
    {
        // Prefer the value eager-loaded via withCount(... as active_member_count).
        // Without this the accessor re-runs a COUNT for every club in a list (N+1),
        // which is brutal against a distant database.
        if (array_key_exists('active_member_count', $this->attributes)) {
            return (int) $this->attributes['active_member_count'];
        }

        return $this->members()->where('status', 'active')->count();
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'first_name', 'last_name', 'edp_number', 'employee_number', 'email', 'password',
    'mobile_number', 'department_id', 'course_id', 'year_level', 'is_admin',
    'pending_club_assignment', 'enrollment_status',
    'firebase_uid', 'role', 'college_id', 'profile_photo_url',
    'last_password_changed', 'is_suspended', 'chat_privacy_acknowledged_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at'            => 'datetime',
            'password'                     => 'hashed',
            'last_password_changed'        => 'datetime',
            'is_suspended'                 => 'boolean',
            'is_admin'                     => 'boolean',
            'year_level'                   => 'integer',
            'pending_club_assignment'      => 'boolean',
            'chat_privacy_acknowledged_at' => 'datetime',
        ];
    }

    /** RA 10173 transparency: private messaging stays closed until the notice is read. */
    public function hasAcknowledgedChatPrivacy(): bool
    {
        return $this->chat_privacy_acknowledged_at !== null;
    }

    /**
     * How this user is described to other people in chat. Deliberately coarse — it
     * never reveals the officer position, admin status, course or year level.
     */
    public function getChatRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'dsa'                           => 'Student Affairs Office',
            'adviser'                       => 'Club Adviser',
            'president', 'treasurer', 'mmo' => 'Club Officer',
            default                         => 'Student',
        };
    }

    /**
     * Registered with their SCC Google account and never set a password: they sign in
     * with Google only, and there is no ClubSync password to check or change.
     */
    public function signsInWithGoogleOnly(): bool
    {
        return $this->password === null;
    }

    /** POLICY.md §Authentication: a password may be changed at most once every 7 calendar days. */
    public const PASSWORD_COOLDOWN_DAYS = 7;

    /** When this user may next change their password. Null if they never have. */
    public function passwordChangeAvailableAt(): ?Carbon
    {
        return $this->last_password_changed?->copy()->addDays(self::PASSWORD_COOLDOWN_DAYS);
    }

    public function canChangePassword(): bool
    {
        $availableAt = $this->passwordChangeAvailableAt();

        return $availableAt === null || now()->greaterThanOrEqualTo($availableAt);
    }

    /** Human-readable school year, e.g. "1st Year". Null for non-student roles. */
    public function getYearLevelLabelAttribute(): ?string
    {
        return $this->year_level ? year_level_label($this->year_level) : null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function getNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}

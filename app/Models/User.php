<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'first_name', 'last_name', 'edp_number', 'employee_number', 'email', 'password',
    'mobile_number', 'department_id', 'course_id', 'year_level', 'is_admin',
    'pending_club_assignment', 'enrollment_status',
    'firebase_uid', 'role', 'college_id', 'profile_photo_url',
    'last_password_changed', 'is_suspended', 'chat_privacy_acknowledged_at',
    'signature_path', 'requested_club_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The club officer posts an administrator appoints, in the order a club lists them.
     * Each is both a users.role value and a club_officers.position value: the role opens
     * the officer pages, the position says which club they hold it in.
     */
    public const POSITION_LABELS = [
        'president'      => 'President',
        'vice_president' => 'Vice President',
        'secretary'      => 'Secretary',
        'treasurer'      => 'Treasurer',
        'auditor'        => 'Auditor',
        'mmo_internal'   => 'Media Mass Officer (Internal)',
        'mmo_external'   => 'Media Mass Officer (External)',
    ];

    /** The single media mass officer post from before it was split. Kept by those who hold it; no longer appointed. */
    public const LEGACY_POSITION_LABELS = ['mmo' => 'Media Mass Officer'];

    /** Every role that opens the officer pages (the `officer` route alias). */
    public const OFFICER_ROLES = [
        'president', 'vice_president', 'secretary', 'treasurer', 'auditor', 'mmo_internal', 'mmo_external', 'mmo',
    ];

    /** Roles held by students: members and club officers. Advisers are employees and never hold one. */
    public const STUDENT_ROLES = [
        'member', 'president', 'vice_president', 'secretary', 'treasurer', 'auditor', 'mmo_internal', 'mmo_external', 'mmo',
    ];

    public static function isOfficerRole(?string $role): bool
    {
        return in_array($role, self::OFFICER_ROLES, true);
    }

    /** "Vice President", "Media Mass Officer (Internal)": how a position or role reads. */
    public static function roleLabel(?string $role): string
    {
        return self::POSITION_LABELS[$role]
            ?? self::LEGACY_POSITION_LABELS[$role]
            ?? match ($role) {
                'dsa'   => 'DSA',
                'vpa'   => 'VPA',
                default => ucfirst((string) $role),
            };
    }

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
            'vpa'                           => 'Office of the VP for Administration',
            'adviser'                       => 'Club Adviser',
            default                         => self::isOfficerRole($this->role) ? 'Club Officer' : 'Student',
        };
    }

    /**
     * Whether this user signs room booking letters (POLICY.md §Event Creation): club
     * officers when they send one, the VPA and club advisers when they approve one.
     */
    public function signsLetters(): bool
    {
        return self::isOfficerRole($this->role) || in_array($this->role, ['adviser', 'vpa'], true);
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

    /**
     * A student's account: registered with an EDP number or a course. Such an account is
     * never made an adviser (POLICY.md §Role-Based Access).
     */
    public function isStudentAccount(): bool
    {
        return $this->edp_number !== null || $this->course_id !== null;
    }

    /** An employee's account: an adviser, or anyone registered with an adviser (employee) number. Never a member or officer. */
    public function isEmployeeAccount(): bool
    {
        return $this->role === 'adviser' || $this->employee_number !== null;
    }

    /** The club this adviser advises (clubs.adviser_id). One club per adviser. */
    public function advisedClub(): HasOne
    {
        return $this->hasOne(Club::class, 'adviser_id');
    }

    /** The club an adviser asked to advise when they registered, until an administrator confirms or declines it. */
    public function requestedClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'requested_club_id');
    }

    public function clubMemberships(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function officerPosts(): HasMany
    {
        return $this->hasMany(ClubOfficer::class);
    }

    public function getNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}

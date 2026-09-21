<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubMember extends Model
{
    protected $fillable = [
        'club_id', 'user_id', 'role', 'date_joined', 'joined_at', 'status',
        'registration_status', 'dsa_remarks', 'submitted_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'date_joined' => 'datetime',
        'joined_at'   => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function semesterStatuses(): HasMany
    {
        return $this->hasMany(MemberSemesterStatus::class);
    }

    public function currentSemesterStatus(): ?MemberSemesterStatus
    {
        $currentSemester = Semester::current()->first();

        if (! $currentSemester) {
            return null;
        }

        return $this->semesterStatuses()
            ->where('semester_id', $currentSemester->id)
            ->orderByDesc('created_at')
            ->first();
    }
}

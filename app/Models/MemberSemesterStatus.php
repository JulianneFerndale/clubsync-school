<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSemesterStatus extends Model
{
    protected $fillable = ['club_member_id', 'semester_id', 'semester_status', 'updated_by'];

    public function clubMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubOfficerRecord extends Model
{
    protected $fillable = [
        'club_id', 'full_name', 'position', 'student_id_number',
        'contact_email', 'academic_year', 'semester', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}

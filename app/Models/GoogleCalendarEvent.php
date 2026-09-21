<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ClubSync record copied into one user's Google Calendar. The database stays
 * the source of truth; this row only remembers where the copy lives.
 */
class GoogleCalendarEvent extends Model
{
    public const ACTIVITY = 'activity';
    public const FEE      = 'fee';
    public const SEMESTER = 'semester';

    protected $fillable = ['user_id', 'source_type', 'source_id', 'slot', 'google_event_id', 'payload_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

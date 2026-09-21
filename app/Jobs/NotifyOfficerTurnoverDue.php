<?php

namespace App\Jobs;

use App\Models\ClubNotification;
use App\Models\User;
use App\Services\OfficerTurnover;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ask each club still running last year's officers to appoint this year's.
 *
 * Runs daily and says nothing on almost all of them: a club is only asked once per
 * school year, and it stops being asked the moment it appoints anybody. The daily
 * schedule is what makes the ask land on the day the year actually turns over rather
 * than on whatever day somebody remembers to look.
 *
 * Nothing here revokes anything. The sitting officers keep working until the club
 * replaces them — this is a reminder and a record, not a cut-off.
 */
class NotifyOfficerTurnoverDue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ADVISER_TITLE = 'New officers needed';
    private const DSA_TITLE     = 'Clubs still to appoint officers';

    public function handle(OfficerTurnover $turnover): void
    {
        $year  = $turnover->academicYear();
        $start = $turnover->yearStart();

        if (! $year || ! $start) {
            return;   // no current semester: the system does not know what year it is
        }

        $pending = $turnover->pendingClubs();

        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $club) {
            if (! $club->adviser_id || $this->alreadyAsked($club->adviser_id, self::ADVISER_TITLE, $start)) {
                continue;
            }

            ClubNotification::create([
                'recipient_id' => $club->adviser_id,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => self::ADVISER_TITLE,
                'body'         => "{$club->name} has not appointed its officers for A.Y. {$year}. "
                    . 'The current officers keep their access until you replace them.',
                'action_url'   => route('clubs.officers.index'),
            ]);
        }

        // One summary for the DSA rather than one notification per club.
        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            if ($this->alreadyAsked($dsaUser->id, self::DSA_TITLE, $start)) {
                continue;
            }

            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => null,
                'title'        => self::DSA_TITLE,
                'body'         => $pending->count() . ' club(s) have not appointed officers for A.Y. ' . $year . ': '
                    . $pending->take(5)->pluck('name')->implode(', ')
                    . ($pending->count() > 5 ? ', and ' . ($pending->count() - 5) . ' more.' : '.'),
                'action_url'   => route('dsa.clubs.index'),
            ]);
        }
    }

    /** Asked already this school year — the daily run must not nag. */
    private function alreadyAsked(int $recipientId, string $title, $since): bool
    {
        return ClubNotification::where('recipient_id', $recipientId)
            ->where('title', $title)
            ->where('created_at', '>=', $since)
            ->exists();
    }
}

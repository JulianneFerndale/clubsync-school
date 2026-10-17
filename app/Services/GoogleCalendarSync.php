<?php

namespace App\Services;

use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Decides what belongs in whose Google Calendar, and brings each user's copy in
 * line with the database.
 *
 *   activity  confirmed, not cancelled → active members it targets, officers, adviser
 *   fee       exists                   → members who still owe it (pending payment)
 *   semester  exists                   → every user (a "begins" and an "ends" event)
 *
 * Only unsuspended users on the institutional Workspace domain are ever synced.
 * Sync is a mirror: it never changes ClubSync data, and a failure for one user is
 * logged and returned, not thrown, so it cannot hold up anyone else's copy.
 */
class GoogleCalendarSync
{
    public function __construct(private GoogleCalendarService $calendar) {}

    /** Ids of the users who should currently have this record on their calendar. */
    public function recipientIds(string $type, int $id): Collection
    {
        $candidates = match ($type) {
            GoogleCalendarEvent::ACTIVITY => $this->activityAudience($id),
            GoogleCalendarEvent::FEE      => FeePayment::where('fee_id', $id)->where('status', 'pending')->pluck('user_id'),
            GoogleCalendarEvent::SEMESTER => Semester::whereKey($id)->exists() ? null : collect(), // null: no filter, everyone
            default                       => throw new InvalidArgumentException("Unknown calendar source [{$type}]."),
        };

        if ($candidates !== null && $candidates->isEmpty()) {
            return collect();
        }

        return $this->syncableUsers()
            ->when($candidates !== null, fn ($query) => $query->whereIn('id', $candidates->unique()->values()))
            ->pluck('id');
    }

    /**
     * The Google event(s) a record maps to, keyed by slot. Empty when the record
     * no longer belongs on anyone's calendar (deleted, unconfirmed, cancelled).
     *
     * @return array<string, array>
     */
    public function entries(string $type, int $id): array
    {
        return match ($type) {
            GoogleCalendarEvent::ACTIVITY => $this->activityEntries(ClubActivity::with('club')->find($id)),
            GoogleCalendarEvent::FEE      => $this->feeEntries(Fee::with('club')->find($id)),
            GoogleCalendarEvent::SEMESTER => $this->semesterEntries(Semester::find($id)),
            default                       => throw new InvalidArgumentException("Unknown calendar source [{$type}]."),
        };
    }

    /**
     * Create, update or remove these users' copies of one record.
     *
     * @param  list<int>  $userIds
     * @return list<int>  The users whose calendar could not be synced this time.
     */
    public function syncUsers(string $type, int $id, array $userIds): array
    {
        if (! $this->calendar->enabled() || $userIds === []) {
            return [];
        }

        $entries    = $this->entries($type, $id);
        $recipients = $entries === [] ? [] : $this->recipientIds($type, $id)->flip()->all();

        $users   = User::whereIn('id', $userIds)->get(['id', 'email'])->keyBy('id');
        $mirrors = GoogleCalendarEvent::where('source_type', $type)
            ->where('source_id', $id)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $failed = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);

            if (! $user) {
                continue; // deleted — their mirror rows went with them
            }

            try {
                // Two jobs touching the same user's copy at once would both create it.
                Cache::lock("google-calendar:{$type}:{$id}:{$userId}", 60)->block(15, fn () => $this->syncUser(
                    $user,
                    $type,
                    $id,
                    isset($recipients[$userId]) ? $entries : [],
                    $mirrors->get($userId, collect()),
                ));
            } catch (Throwable $e) {
                Log::warning("Google Calendar sync failed for user #{$userId}, {$type} #{$id}: " . $e->getMessage());
                $failed[] = $userId;
            }
        }

        return $failed;
    }

    /** @param  array<string, array>  $wanted  slot => payload this user should have */
    private function syncUser(User $user, string $type, int $id, array $wanted, Collection $mirrors): void
    {
        // An address the service account cannot act as (e.g. changed off the
        // institutional domain): nothing can be written, so just forget the copies.
        if (! $this->calendar->canSyncFor($user->email)) {
            GoogleCalendarEvent::whereKey($mirrors->pluck('id'))->delete();

            return;
        }

        foreach ($wanted as $slot => $payload) {
            $hash   = sha1(json_encode($payload));
            $mirror = $mirrors->firstWhere('slot', $slot);

            if ($mirror?->payload_hash === $hash) {
                continue;
            }

            if ($mirror && $this->calendar->updateEvent($user->email, $mirror->google_event_id, $payload)) {
                $mirror->update(['payload_hash' => $hash]);

                continue;
            }

            // New, or the user deleted our copy in Google — create a fresh one.
            GoogleCalendarEvent::updateOrCreate(
                ['user_id' => $user->id, 'source_type' => $type, 'source_id' => $id, 'slot' => $slot],
                ['google_event_id' => $this->calendar->createEvent($user->email, $payload), 'payload_hash' => $hash],
            );
        }

        foreach ($mirrors->whereNotIn('slot', array_keys($wanted)) as $mirror) {
            $this->calendar->deleteEvent($user->email, $mirror->google_event_id);
            $mirror->delete();
        }
    }

    private function syncableUsers()
    {
        return User::query()
            ->where(fn ($query) => $query->where('is_suspended', false)->orWhereNull('is_suspended'))
            ->whereRaw('LOWER(email) LIKE ?', ['%@' . strtolower((string) config('google.calendar.domain'))]);
    }

    private function activityAudience(int $id): Collection
    {
        $activity = ClubActivity::with('club')->find($id);

        if (! $activity?->club || ! $activity->isConfirmed()) {
            return collect();
        }

        $members = ClubMember::where('club_id', $activity->club_id)
            ->where('status', 'active')
            ->with('user:id,year_level')
            ->get()
            ->filter(fn (ClubMember $member) => $member->user && $activity->reachesYearLevel($member->user->year_level))
            ->pluck('user_id');

        return $members
            ->merge(ClubOfficer::where('club_id', $activity->club_id)->pluck('user_id'))
            ->when($activity->club->adviser_id, fn ($ids) => $ids->push($activity->club->adviser_id));
    }

    private function activityEntries(?ClubActivity $activity): array
    {
        if (! $activity?->club || ! $activity->isConfirmed()) {
            return [];
        }

        $tz    = $this->timezone();
        $date  = $activity->date->toDateString();
        $start = Carbon::parse($date . ' ' . $activity->time_start, $tz);
        $end   = Carbon::parse($date . ' ' . ($activity->time_end ?: $activity->time_start), $tz);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addHour();
        }

        return ['main' => $this->payload(GoogleCalendarEvent::ACTIVITY, $activity->id, [
            'summary'     => $activity->title . ' (' . ($activity->club->acronym ?: $activity->club->name) . ')',
            'description' => implode("\n\n", array_filter([
                'Club: ' . $activity->club->name,
                $activity->description,
                $activity->purpose ? 'Purpose: ' . $activity->purpose : null,
            ])),
            'location'    => $activity->venue,
            'start'       => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $tz],
            'end'         => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => $tz],
        ])];
    }

    private function feeEntries(?Fee $fee): array
    {
        // A cancelled fee is no longer due from anyone, so it comes off every calendar.
        if (! $fee?->club || $fee->isCancelled()) {
            return [];
        }

        return ['main' => $this->allDay(GoogleCalendarEvent::FEE, $fee->id, $fee->due_date, [
            'summary'     => 'Fee due: ' . $fee->title . ' (' . ($fee->club->acronym ?: $fee->club->name) . ')',
            'description' => implode("\n\n", [
                'Club: ' . $fee->club->name,
                'Amount: ₱' . number_format((float) $fee->amount, 2),
                'Academic period: ' . $fee->academic_period,
                'Pay your club treasurer on or before this date. This reminder is removed once your payment is recorded.',
            ]),
        ])];
    }

    private function semesterEntries(?Semester $semester): array
    {
        if (! $semester) {
            return [];
        }

        return [
            'start' => $this->allDay(GoogleCalendarEvent::SEMESTER, $semester->id, $semester->start_date, [
                'summary' => $semester->label . ' begins',
            ], 'start'),
            'end' => $this->allDay(GoogleCalendarEvent::SEMESTER, $semester->id, $semester->end_date, [
                'summary' => $semester->label . ' ends',
            ], 'end'),
        ];
    }

    /** An all-day event that does not mark the user as busy. */
    private function allDay(string $type, int $id, Carbon $date, array $fields, string $slot = 'main'): array
    {
        return $this->payload($type, $id, $fields + [
            'start'        => ['date' => $date->toDateString()],
            'end'          => ['date' => $date->copy()->addDay()->toDateString()], // Google's end date is exclusive
            'transparency' => 'transparent',
        ], $slot);
    }

    private function payload(string $type, int $id, array $fields, string $slot = 'main'): array
    {
        $fields['description'] = trim(($fields['description'] ?? '') . "\n\n"
            . 'Added by SCC ClubSync. Edits made here are replaced whenever the record changes in ClubSync.');

        // Tags the copy so it can be traced back to its record from the Google side.
        $fields['extendedProperties'] = ['private' => ['clubsync' => "{$type}:{$id}:{$slot}"]];

        return $fields;
    }

    private function timezone(): string
    {
        return config('google.calendar.timezone', 'Asia/Manila');
    }
}

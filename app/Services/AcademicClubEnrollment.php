<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Put a student in the queue for the academic club(s) their course maps to.
 *
 * The course→club mapping is the trigger, not the membership: POLICY.md
 * §Registration is explicit that enrolment "is NOT automatic and NOT active on
 * sign-up". Everyone queued here is a **pending applicant**, and stays one until the
 * club officers accept them and the adviser gives final approval — the same two-step
 * the non-academic clubs use.
 *
 * Both the ways a student can acquire a course go through here — finishing
 * registration, and picking one after the fact — so the two cannot drift apart on
 * what enrolment means or on what happens when a course maps to nothing.
 */
class AcademicClubEnrollment
{
    /**
     * Queue $user for every active academic club mapped to $course.
     *
     * A course that maps to no club is not a failure: the student is flagged
     * `Pending Club Assignment` and the DSA is told, per POLICY.md §Registration, so
     * somebody picks it up rather than the student silently belonging nowhere.
     *
     * @return Collection<int, Club> the clubs they were queued for
     */
    public function enroll(User $user, Course $course): Collection
    {
        $clubs = Club::where('club_type', 'Academic')
            ->where('is_active', true)
            ->whereHas('courses', fn ($q) => $q->where('courses.id', $course->id))
            ->get();

        foreach ($clubs as $club) {
            ClubMember::firstOrCreate(
                ['club_id' => $club->id, 'user_id' => $user->id],
                ['role' => 'Member', 'status' => 'pending', 'date_joined' => now(), 'joined_at' => now()],
            );
        }

        $unmapped = $clubs->isEmpty();

        // Re-flag on every run: a student whose course was unmapped and has since been
        // mapped should stop being flagged without anyone clearing it by hand.
        $user->forceFill(['pending_club_assignment' => $unmapped])->save();

        if ($unmapped) {
            $this->alertDsa($user, $course);
        }

        return $clubs;
    }

    /**
     * Tell the DSA a student is waiting on a club assignment.
     *
     * In-system, because POLICY.md §Notifications makes the in-app notification the
     * system of record. No club id: there is no club yet, which is the whole point.
     */
    private function alertDsa(User $user, Course $course): void
    {
        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => null,
                'title'        => 'Student pending club assignment',
                'body'         => "{$user->name} selected {$course->name}, which is not mapped to any academic club. "
                    . 'Their club assignment needs review.',
                'action_url'   => route('dsa.dashboard'),
            ]);
        }
    }
}

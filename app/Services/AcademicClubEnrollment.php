<?php

namespace App\Services;

use App\Jobs\SyncClubMemberGoogleCalendar;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Make a student a member of the academic club(s) their course maps to.
 *
 * POLICY.md §Registration: once a student's enrollment is verified, they are an
 * **active member** of their course's academic club at once — no officer acceptance
 * and no adviser approval. The verified Certificate of Enrollment or school ID is the
 * proof that they belong; there is nothing left for anyone to decide. Joining any
 * other club by hand still goes through the officers and the adviser.
 *
 * Both the ways a student can acquire a course go through here — finishing
 * registration, and picking one after the fact — so the two cannot drift apart on
 * what enrolment means or on what happens when a course maps to nothing.
 */
class AcademicClubEnrollment
{
    /**
     * Make $user an active member of every active academic club mapped to $course.
     *
     * Safe to run again: someone already a member is left as they are. A request
     * still waiting — a Join pressed by hand before the course was recorded — becomes
     * the membership. One an adviser turned down is left alone; a person decided it.
     *
     * A course that maps to no club is not a failure: the student is flagged
     * `Pending Club Assignment` and the DSA is told, per POLICY.md §Registration, so
     * somebody picks it up rather than the student silently belonging nowhere.
     *
     * @return Collection<int, Club> the clubs mapped to the course
     */
    public function enroll(User $user, Course $course): Collection
    {
        $clubs = Club::where('club_type', 'Academic')
            ->where('is_active', true)
            ->whereHas('courses', fn ($q) => $q->where('courses.id', $course->id))
            ->get();

        foreach ($clubs as $club) {
            $membership = ClubMember::firstOrNew(['club_id' => $club->id, 'user_id' => $user->id]);

            if ($membership->exists && ($membership->status === 'active' || $membership->registration_status === 'rejected')) {
                continue;
            }

            $membership->fill([
                'role'                => $membership->role ?: 'Member',
                'status'              => 'active',
                'registration_status' => 'approved',
                // Nobody approved it: the verified course did.
                'approved_by'         => null,
                'approved_at'         => now(),
                'date_joined'         => $membership->date_joined ?? now(),
                'joined_at'           => $membership->joined_at ?? now(),
            ])->save();

            $this->notify($user, $course, $club);

            // A member gets the club's upcoming activities on their Google Calendar,
            // as one approved by an adviser does.
            SyncClubMemberGoogleCalendar::dispatch($user->id, $club->id);
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
     * What to tell the student about the clubs enroll() returned — shared by
     * registration and the course step so both say the same thing. POLICY.md
     * §Registration: a student whose course maps to no club is told their club
     * assignment is pending review.
     *
     * @param  Collection<int, Club>  $clubs
     */
    public function summary(Collection $clubs, Course $course): string
    {
        return $clubs->isEmpty()
            ? 'No academic club is mapped to ' . $course->name
                . ' yet, so the Student Affairs Office has been asked to review your club assignment.'
            : 'You are now a member of ' . $clubs->pluck('name')->implode(', ') . ', the academic club for your course.';
    }

    /**
     * The student hears they are in — they never pressed Join, so nothing else tells
     * them. The officers and the adviser hear it for their records; there is nothing
     * for them to approve.
     */
    private function notify(User $user, Course $course, Club $club): void
    {
        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => $club->id,
            'title'        => "Welcome to {$club->name}!",
            'body'         => "{$club->name} is the academic club for {$course->name}, so you are now a member. "
                . 'Its activities and announcements are open to you.',
            'action_url'   => route('member.clubs.show', $club),
        ]);

        $staff = ClubOfficer::where('club_id', $club->id)->pluck('user_id')
            ->push($club->adviser_id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $user->id);

        foreach ($staff as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club->id,
                'title'        => 'New member joined',
                'body'         => "{$user->name} joined {$club->name} as a {$course->name} student. "
                    . 'Academic club members join through their verified course, so there is nothing to approve.',
                'action_url'   => route('clubs.members.index'),
            ]);
        }
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

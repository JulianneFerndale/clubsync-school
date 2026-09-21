<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubOfficer;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Which clubs have not replaced their officers for the school year they are in.
 *
 * Officers turn over with the school year: the summer semester closes one and the
 * next begins, and each club appoints a new set (POLICY.md §Club Registry). Nothing
 * is taken away on a date — the sitting officers keep working until they are actually
 * replaced, so a club is never left unable to run itself because a term rolled over
 * on a weekend. What changes is that the club is *asked*, and the DSA can see who has
 * not answered.
 *
 * "Replaced" is read off `club_officers.assigned_at`: an appointment made on or after
 * the school year began belongs to this year. That needs no new column and no
 * bookkeeping anyone has to remember to do.
 */
class OfficerTurnover
{
    /** The school year in progress, or null when no semester is flagged current. */
    public function academicYear(): ?string
    {
        return Semester::currentAcademicYear();
    }

    /** The day the school year in progress began. */
    public function yearStart(): ?Carbon
    {
        $year = $this->academicYear();

        return $year ? Semester::academicYearStart($year) : null;
    }

    /**
     * Has $club appointed any officer since this school year began?
     *
     * Null year — nothing flagged current — is not "everyone is overdue"; it means the
     * system does not know what year it is, and it says so by treating nobody as
     * pending rather than flagging all 42 clubs on a missing setting.
     */
    public function isPending(Club $club): bool
    {
        $start = $this->yearStart();

        if (! $start) {
            return false;
        }

        return ! ClubOfficer::where('club_id', $club->id)
            ->where('assigned_at', '>=', $start)
            ->exists();
    }

    /**
     * Every active club still owing a new set of officers.
     *
     * @return Collection<int, Club>
     */
    public function pendingClubs(): Collection
    {
        $start = $this->yearStart();

        if (! $start) {
            return collect();
        }

        return Club::where('is_active', true)
            ->whereDoesntHave('officers', fn ($q) => $q->where('assigned_at', '>=', $start))
            ->with('adviserUser')
            ->orderBy('name')
            ->get();
    }
}

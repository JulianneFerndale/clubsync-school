<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * DSA oversight of club activities: read-only.
 *
 * Approving an activity belongs to the club adviser (POLICY.md §Event Creation).
 * The DSA watches every club from here and reports on them, but does not decide —
 * the one exception being a club with no adviser, where the submission notification
 * comes here instead so nothing waits on nobody.
 */
class ActivityController extends Controller
{
    public function monitor(Request $request): View
    {
        $query = ClubActivity::with('club')->orderBy('date');

        if ($request->filled('club_id')) {
            $query->where('club_id', $request->input('club_id'));
        }

        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->input('activity_type'));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('semester_id')) {
            $semester = Semester::find($request->input('semester_id'));

            if ($semester) {
                $query->whereBetween('date', [$semester->start_date, $semester->end_date]);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        $activities = $query->paginate(20)->withQueryString();

        $clubs     = Club::orderBy('name')->get();
        $semesters = Semester::orderByDesc('start_date')->get();

        return view('dsa.activities.monitor', compact('activities', 'clubs', 'semesters'));
    }

    public function show(ClubActivity $event): View
    {
        $event->load('club', 'creator', 'approvedBy', 'changeLogs.changedBy');

        return view('dsa.activities.show', compact('event'));
    }

    public function downloadLetter(ClubActivity $event)
    {
        if (! $event->approval_letter_path) {
            abort(404);
        }

        return Storage::disk('local')->download($event->approval_letter_path);
    }
}

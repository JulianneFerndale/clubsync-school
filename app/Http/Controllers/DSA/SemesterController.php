<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\AuditLog;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The academic calendar the semester-based features run on: CHED report semesters,
 * member presence, the compliance scan, and the end-of-semester narrative reports,
 * which are drafted the day after a semester's end date.
 */
class SemesterController extends Controller
{
    public function index(): View
    {
        $semesters = Semester::withCount([
            'narrativeReports',
            'narrativeReports as submitted_reports_count' => fn ($query) => $query->where('status', 'submitted'),
        ])->orderByDesc('start_date')->get();

        return view('dsa.semesters.index', compact('semesters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $semester = DB::transaction(function () use ($validated) {
            if ($validated['is_current']) {
                Semester::where('is_current', true)->update(['is_current' => false]);
            }

            return Semester::create($validated);
        });

        AuditLog::record('semester.created', $semester, $validated);

        // "Begins" and "ends" go onto every user's Google Calendar.
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::SEMESTER, $semester->id);

        return redirect()->route('dsa.semesters.index')->with('success', "{$semester->label} added.");
    }

    public function update(Request $request, Semester $semester): RedirectResponse
    {
        $validated = $this->validated($request, $semester);

        DB::transaction(function () use ($validated, $semester) {
            if ($validated['is_current']) {
                Semester::where('is_current', true)->whereKeyNot($semester->id)->update(['is_current' => false]);
            }

            $semester->update($validated);
        });

        AuditLog::record('semester.updated', $semester, $validated);

        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::SEMESTER, $semester->id);

        return redirect()->route('dsa.semesters.index')->with('success', "{$semester->label} updated.");
    }

    private function validated(Request $request, ?Semester $editing = null): array
    {
        $validated = $request->validate([
            'label'      => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
        ], [
            'end_date.after' => 'The semester must end after it starts.',
        ]);

        // Activities are assigned to a semester by date, so two semesters may not overlap.
        $overlap = Semester::whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "These dates overlap {$overlap->label} ({$overlap->start_date->format('M j, Y')} – {$overlap->end_date->format('M j, Y')}).",
            ]);
        }

        $validated['is_current'] = $request->boolean('is_current');

        return $validated;
    }
}

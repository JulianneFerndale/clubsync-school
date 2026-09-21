<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubOfficer;
use App\Models\ClubOfficerRecord;
use App\Services\OfficerTurnover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubOfficerRecordController extends Controller
{
    public function __construct(private OfficerTurnover $turnover) {}

    private function currentClub(): ?Club
    {
        if (auth_role() === 'adviser') {
            return Club::where('adviser_id', auth_user_id())->first();
        }

        return ClubOfficer::where('user_id', auth_user_id())->with('club')->first()?->club;
    }

    private function rules(): array
    {
        return [
            'full_name'         => ['required', 'string', 'max:255'],
            'position'          => ['required', 'string', 'max:100'],
            'student_id_number' => ['required', 'string', 'max:50'],
            'contact_email'     => ['nullable', 'email', 'max:255'],
            'academic_year'     => ['required', 'string', 'max:20'],
            'semester'          => ['required', 'in:1st,2nd,summer'],
        ];
    }

    public function index(): View
    {
        $club = $this->currentClub();

        $records = $club
            ? ClubOfficerRecord::where('club_id', $club->id)
                ->orderByDesc('academic_year')
                ->orderBy('semester')
                ->orderBy('position')
                ->get()
            : collect();

        // Surfaced on the page where it is acted on: this is where the adviser
        // appoints the new set.
        $turnoverPending = $club ? $this->turnover->isPending($club) : false;
        $academicYear    = $this->turnover->academicYear();

        return view('clubs.officers.index', compact('club', 'records', 'turnoverPending', 'academicYear'));
    }

    /** Only the club adviser may add/edit/archive officer records. */
    private function requireAdviser(): void
    {
        abort_unless(auth_role() === 'adviser', 403);
    }

    public function create(): View
    {
        $this->requireAdviser();

        $club = $this->currentClub();

        return view('clubs.officers.create', compact('club'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdviser();

        $club = $this->currentClub();

        if (! $club) {
            return back()->withErrors(['club' => 'You are not associated with any club.']);
        }

        $validated = $request->validate($this->rules());
        $validated['club_id']   = $club->id;
        $validated['is_active'] = true;

        ClubOfficerRecord::create($validated);

        return redirect()->route('clubs.officers.index')->with('success', 'Officer record added.');
    }

    public function edit(ClubOfficerRecord $record): View
    {
        $this->requireAdviser();

        $club = $this->currentClub();

        if (! $club || $record->club_id !== $club->id) {
            abort(403);
        }

        return view('clubs.officers.edit', compact('club', 'record'));
    }

    public function update(Request $request, ClubOfficerRecord $record): RedirectResponse
    {
        $this->requireAdviser();

        $club = $this->currentClub();

        if (! $club || $record->club_id !== $club->id) {
            abort(403);
        }

        $validated = $request->validate($this->rules());

        $record->update($validated);

        return redirect()->route('clubs.officers.index')->with('success', 'Officer record updated.');
    }

    public function archive(ClubOfficerRecord $record): RedirectResponse
    {
        $this->requireAdviser();

        $club = $this->currentClub();

        if (! $club || $record->club_id !== $club->id) {
            abort(403);
        }

        $record->update(['is_active' => false]);

        return back()->with('success', 'Officer record archived.');
    }
}

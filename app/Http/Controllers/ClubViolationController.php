<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyDsaOfViolationResolution;
use App\Models\AiNotificationQueue;
use App\Models\Club;
use App\Models\ClubOfficer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubViolationController extends Controller
{
    private function currentClub(): ?Club
    {
        if (auth_role() === 'adviser') {
            return Club::where('adviser_id', auth_user_id())->first();
        }

        return ClubOfficer::where('user_id', auth_user_id())->with('club')->first()?->club;
    }

    public function index(): View
    {
        $club = $this->currentClub();

        $violations = $club
            ? AiNotificationQueue::where('club_id', $club->id)
                ->where('status', 'edited_sent')
                ->orderByDesc('sent_at')
                ->get()
            : collect();

        return view('clubs.violations.index', compact('club', 'violations'));
    }

    public function resolve(Request $request, AiNotificationQueue $violation): RedirectResponse
    {
        $club = $this->currentClub();

        if (! $club || $violation->club_id !== $club->id) {
            abort(403);
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'min:5'],
        ], [
            'resolution_note.required' => 'Please describe how this was resolved.',
        ]);

        $violation->update([
            'is_resolved'     => true,
            'resolution_note' => $validated['resolution_note'],
            'resolved_at'     => now(),
            'resolved_by'     => auth_user_id(),
        ]);

        NotifyDsaOfViolationResolution::dispatch($violation->id);

        return redirect()->route('clubs.violations.index')->with('success', 'Violation marked as resolved.');
    }
}

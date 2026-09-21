<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNarrative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NarrativeController extends Controller
{
    private function adviserClub(): ?Club
    {
        return Club::where('adviser_id', auth_user_id())->first();
    }

    public function index(): View
    {
        $club = $this->adviserClub();

        $pending = $club
            ? ClubNarrative::where('club_id', $club->id)
                ->pendingReview()
                ->with('activity')
                ->orderBy('created_at')
                ->get()
            : collect();

        $reviewed = $club
            ? ClubNarrative::where('club_id', $club->id)
                ->whereIn('status', ['published', 'discarded'])
                ->orderByDesc('reviewed_at')
                ->take(20)
                ->get()
            : collect();

        return view('adviser.narratives.index', compact('club', 'pending', 'reviewed'));
    }

    public function show(ClubNarrative $narrative): View
    {
        $club = $this->adviserClub();

        if (! $club || $narrative->club_id !== $club->id) {
            abort(403);
        }

        $narrative->load('activity');

        return view('adviser.narratives.show', compact('narrative', 'club'));
    }

    /**
     * Approve — and optionally edit — the narrative, publishing it to the bulletin.
     * This is the mandatory human gate required by POLICY.md (AI & Automation):
     * publishing happens here, never in the generation job.
     */
    public function approve(Request $request, ClubNarrative $narrative): RedirectResponse
    {
        $club = $this->adviserClub();

        if (! $club || $narrative->club_id !== $club->id) {
            abort(403);
        }

        if ($narrative->status !== 'pending_review') {
            return back()->withErrors(['status' => 'This narrative has already been reviewed.']);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:5'],
        ], [
            'content.required' => 'A narrative cannot be published empty.',
        ]);

        $narrative->update([
            'adviser_edited_content' => $validated['content'] !== $narrative->draft_content ? $validated['content'] : null,
            'status'                 => 'published',
            'reviewed_by'            => auth_user_id(),
            'reviewed_at'            => now(),
            'published_at'           => now(),
        ]);

        AuditLog::record('narrative.published', $narrative, [
            'club_id' => $narrative->club_id,
            'edited'  => $narrative->adviser_edited_content !== null,
        ]);

        return redirect()->route('adviser.narratives.index')
            ->with('success', 'Narrative approved and published to the bulletin.');
    }

    public function discard(ClubNarrative $narrative): RedirectResponse
    {
        $club = $this->adviserClub();

        if (! $club || $narrative->club_id !== $club->id) {
            abort(403);
        }

        if ($narrative->status !== 'pending_review') {
            return back()->withErrors(['status' => 'This narrative has already been reviewed.']);
        }

        $narrative->update([
            'status'      => 'discarded',
            'reviewed_by' => auth_user_id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('narrative.discarded', $narrative, ['club_id' => $narrative->club_id]);

        return redirect()->route('adviser.narratives.index')
            ->with('success', 'Narrative discarded.');
    }
}

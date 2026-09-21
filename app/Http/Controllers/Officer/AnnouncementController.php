<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyMembersOfAnnouncement;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\ClubOfficer;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    private function officerClub(): ?\App\Models\Club
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->first()
            ?->club;
    }

    public function index(): View
    {
        $club = $this->officerClub();

        $announcements = $club
            ? Announcement::where('club_id', $club->id)
                ->where('author_id', auth_user_id())
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('officer.announcements.index', compact('club', 'announcements'));
    }

    public function create(): View
    {
        $club        = $this->officerClub();
        $aiAvailable = $this->gemini->isAvailable();

        return view('officer.announcements.create', compact('club', 'aiAvailable'));
    }

    public function store(Request $request): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return back()->withErrors(['club' => 'You are not assigned to any club.']);
        }

        $validated = $request->validate([
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:10'],
            'type'    => ['required', 'in:announcement,letter'],
        ], [
            'content.required' => 'Content is required.',
            'content.min'      => 'Content must be at least 10 characters.',
            'type.required'    => 'Please select a type.',
        ]);

        // Officers publish their own club's announcements (POLICY.md §Announcements
        // & Content Workflow) — there is no review queue in front of this. An
        // AI-assisted announcement is still never published unreviewed: the draft is
        // returned to the form and the officer is the human who reads, edits and
        // posts it (POLICY.md §AI & Automation).
        $announcement = Announcement::create([
            'club_id'      => $club->id,
            'author_id'    => auth_user_id(),
            'title'        => $validated['title'] ?? null,
            'content'      => $validated['content'],
            'type'         => $validated['type'],
            'status'       => 'published',
            'published_at' => now(),
            'ai_assisted'  => $request->boolean('ai_assisted'),
        ]);

        NotifyMembersOfAnnouncement::dispatch($announcement->id);

        return redirect()->route('officer.announcements.show', $announcement)
            ->with('success', 'Announcement published.');
    }

    public function show(Announcement $announcement): View
    {
        $club = $this->officerClub();

        if (! $club || $announcement->club_id !== $club->id || $announcement->author_id !== auth_user_id()) {
            abort(403);
        }

        return view('officer.announcements.show', compact('announcement', 'club'));
    }

    public function edit(Announcement $announcement): View
    {
        $club = $this->officerClub();

        if (! $club || $announcement->club_id !== $club->id || $announcement->author_id !== auth_user_id()) {
            abort(403);
        }

        $aiAvailable = $this->gemini->isAvailable();

        return view('officer.announcements.edit', compact('announcement', 'club', 'aiAvailable'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $announcement->club_id !== $club->id || $announcement->author_id !== auth_user_id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title'   => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:10'],
            'type'    => ['required', 'in:announcement,letter'],
        ]);

        $announcement->update([
            'title'       => $validated['title'] ?? null,
            'content'     => $validated['content'],
            'type'        => $validated['type'],
            'ai_assisted' => $announcement->ai_assisted || $request->boolean('ai_assisted'),
        ]);

        return redirect()->route('officer.announcements.show', $announcement)
            ->with('success', 'Announcement updated.');
    }

    /**
     * Publish an announcement that is not published yet.
     *
     * New announcements publish the moment they are written, so this exists for the
     * ones left behind by the retired adviser review queue: anything still sitting in
     * `draft`, `pending_review` or `revision_required` when the gate was removed. It
     * is the officer's own announcement either way, and nothing publishes without
     * them pressing the button.
     */
    public function publish(Announcement $announcement): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $announcement->club_id !== $club->id || $announcement->author_id !== auth_user_id()) {
            abort(403);
        }

        if ($announcement->status === 'published') {
            return back()->withErrors(['status' => 'This announcement is already published.']);
        }

        $announcement->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        NotifyMembersOfAnnouncement::dispatch($announcement->id);

        return redirect()->route('officer.announcements.show', $announcement)
            ->with('success', 'Announcement published.');
    }

    // AJAX endpoint — returns generated draft content as JSON
    public function aiDraft(Request $request): JsonResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return response()->json(['error' => 'No club assigned.'], 403);
        }

        if (! $this->gemini->isAvailable()) {
            return response()->json(['error' => 'AI is not configured.'], 503);
        }

        // The AI panel asks for a title OR a description of the topic, so accept either.
        // Validated by hand rather than $request->validate(): the page's fetch() sends no
        // Accept header, so a thrown ValidationException would answer with a 302 redirect
        // and the officer would see "Network error" instead of the actual reason.
        $validator = Validator::make($request->all(), [
            'title'   => ['nullable', 'required_without:context', 'string', 'max:255'],
            'type'    => ['required', 'in:announcement,letter'],
            'context' => ['nullable', 'required_without:title', 'string', 'max:500'],
        ], [
            'title.required_without'   => 'Enter a title or describe the topic before generating.',
            'context.required_without' => 'Enter a title or describe the topic before generating.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $title   = trim((string) $request->input('title', ''));
        $type    = $request->input('type', 'announcement');
        $context = trim((string) $request->input('context', ''));

        $content = $this->gemini->draftAnnouncement($title, $type, $club->name, $context);

        if ($content === null) {
            AuditLog::record('ai.announcement.draft_failed', $club, [
                'type'  => $type,
                'title' => $title,
            ]);

            return response()->json(['error' => 'AI generation failed. Please try again.'], 500);
        }

        $content = trim($content);

        // POLICY.md §Data Privacy — every AI content generation event is audited.
        // The draft itself is returned to the officer only; nothing is persisted or
        // published here (POLICY.md §AI & Automation — the human gate is the officer
        // reviewing the form before pressing Save Draft).
        AuditLog::record('ai.announcement.drafted', $club, [
            'type'           => $type,
            'title'          => $title,
            'used_context'   => $context !== '',
            'content_length' => strlen($content),
        ]);

        return response()->json(['content' => $content]);
    }
}

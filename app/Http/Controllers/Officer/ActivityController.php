<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateClubNarrativeJob;
use App\Jobs\NotifyMemberOfAttendance;
use App\Jobs\NotifyMembersOfActivity;
use App\Jobs\NotifyVpaOfBookingLetter;
use App\Jobs\SyncToGoogleCalendars;
use App\Models\ActivityChangeLog;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\GoogleCalendarEvent;
use App\Models\User;
use App\Services\BookingLetter;
use App\Services\FirebaseService;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ActivityController extends Controller
{
    private const YEAR_LEVELS = [
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
    ];

    /**
     * Normalize the year-level checkboxes into a clean, sorted int array.
     * An empty selection becomes null, meaning "open to all year levels".
     */
    private function normalizeYearLevels(Request $request): ?array
    {
        $levels = array_values(array_unique(array_map(
            'intval',
            $request->input('target_year_levels', [])
        )));

        sort($levels);

        return $levels ?: null;
    }

    /**
     * The club's own membership, as the figures the AI draft has to answer to: how
     * many active members it has and how they split across year levels.
     *
     * Deliberately the same set NotifyMembersOfActivity announces to — active
     * memberships, pending applicants excluded — so a drafted turnout can never be
     * larger than the audience the activity actually reaches. A member with no year
     * level on record still counts in the total, matching
     * ClubActivity::reachesYearLevel(), which includes them in every activity.
     *
     * @return array{total: int, by_year: array<int, int>}
     */
    private function rosterFacts(Club $club): array
    {
        $rows = ClubMember::where('club_members.club_id', $club->id)
            ->where('club_members.status', 'active')
            ->join('users', 'users.id', '=', 'club_members.user_id')
            ->selectRaw('users.year_level as level, count(*) as total')
            ->groupBy('users.year_level')
            ->pluck('total', 'level');

        $byYear = [];
        $total  = 0;

        foreach ($rows as $level => $count) {
            $total += (int) $count;

            if ((int) $level >= 1 && (int) $level <= 4) {
                $byYear[(int) $level] = (int) $count;
            }
        }

        return ['total' => $total, 'by_year' => $byYear];
    }

    public function __construct(
        private FirebaseService $firebase,
        private GeminiService $gemini,
        private BookingLetter $letters,
    ) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Reject the submission when the chosen venue is already taken for an
     * overlapping slot, returning the validation error to send back.
     *
     * The message deliberately withholds the other activity's title when the
     * clash belongs to a different club: POLICY.md ("Cross-club data access is
     * blocked ... users cannot access data from clubs they are not members or
     * officers of") rules out leaking one club's schedule detail to another.
     * The occupied time range is disclosed either way — an officer cannot pick a
     * free slot without it, and it is the minimum needed to do so.
     *
     * @return array<string, string>|null
     */
    /**
     * PostgreSQL returns time columns as H:i:s, so a form re-submitted without touching
     * its time pickers can send "07:10:00". Trim to H:i before the H:i rule runs, so an
     * officer can save an edit without re-entering the times.
     */
    private function normalizeTimes(Request $request): void
    {
        foreach (['time_start', 'time_end'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $request->merge([$field => substr($value, 0, 5)]);
            }
        }
    }

    /**
     * Reject a new activity whose start has already gone by.
     *
     * The date rule only reaches as far as midnight: "today" still satisfies it at
     * 4 PM for an activity that started at 8 AM. An officer cannot mean to schedule
     * one into the past — nobody can attend it, no reminder can fire for it, and the
     * DSA would be asked to approve something that has already happened.
     *
     * Creation only. POLICY.md: "Officers may edit any activity at any time", which
     * includes activities that have already taken place, so update() does not run it.
     *
     * @return array<string, string>|null
     */
    private function pastStartError(array $validated): ?array
    {
        $start = Carbon::parse($validated['date'] . ' ' . $validated['time_start']);

        if (! $start->isPast()) {
            return null;
        }

        $sameDay = $start->isSameDay(now());

        return ['time_start' => $sameDay
            ? 'That start time has already passed — it is now ' . now()->format('g:i A')
                . '. Set the activity for a later time today, or a future date.'
            : 'That start time is in the past. Set the activity for a future date and time.',
        ];
    }

    private function venueConflictError(array $validated, ?ClubActivity $editing = null): ?array
    {
        $conflict = ClubActivity::findVenueConflict(
            $validated['venue'],
            $validated['date'],
            $validated['time_start'],
            $validated['time_end'],
            $editing?->id,
        );

        if (! $conflict) {
            return null;
        }

        $window = substr($conflict->time_start, 0, 5) . ' to ' . substr($conflict->time_end, 0, 5);
        $day    = Carbon::parse($validated['date'])->format('F j, Y');
        $ours   = $editing ? $conflict->club_id === $editing->club_id : $conflict->club_id === $this->officerClub()?->id;

        return ['venue' => $ours
            ? "{$validated['venue']} is already booked for \"{$conflict->title}\" from {$window} on {$day}. Choose a different venue or time."
            : "{$validated['venue']} is already booked by another club from {$window} on {$day}. Choose a different venue or time.",
        ];
    }

    /**
     * The room booking letter must ask for the room actually being booked: a letter the
     * VPA approves for one room while the activity holds another would book neither.
     *
     * @return array<string, string>|null
     */
    private function letterVenueError(string $body, string $venue): ?array
    {
        if (str_contains(mb_strtolower($body), mb_strtolower($venue))) {
            return null;
        }

        return ['letter_body' => "The letter must name the room you are booking ({$venue}). Draft it again, or add the room to it."];
    }

    private function officerClub(): ?\App\Models\Club
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->first()
            ?->club;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $club = $this->officerClub();

        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year',  now()->year);

        // Clamp to valid range
        $month = max(1, min(12, $month));

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $monthEvents = collect();
        $upcomingGrouped = collect();

        if ($club) {
            $monthEvents = ClubActivity::where('club_id', $club->id)
                ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->orderBy('date')
                ->get()
                ->groupBy(fn ($e) => $e->date->format('Y-m-d'));

            $upcomingGrouped = ClubActivity::where('club_id', $club->id)
                ->where('date', '>=', today())
                ->orderBy('date')
                ->get()
                ->groupBy(fn ($e) => $e->date->format('F Y'));
        }

        $prevMonth = $startOfMonth->copy()->subMonth();
        $nextMonth = $startOfMonth->copy()->addMonth();

        return view('officer.activities.index', compact(
            'club', 'month', 'year', 'startOfMonth',
            'monthEvents', 'upcomingGrouped',
            'prevMonth', 'nextMonth',
        ));
    }

    public function create(): View
    {
        $club  = $this->officerClub();
        $venues = ClubActivity::VENUES;
        $yearLevels = self::YEAR_LEVELS;
        $aiAvailable = $this->gemini->isReady();

        return view('officer.activities.create', compact('club', 'venues', 'yearLevels', 'aiAvailable'));
    }

    public function store(Request $request): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return back()->withErrors(['club' => 'You are not assigned to any club.']);
        }

        $this->normalizeTimes($request);

        $validated = $request->validate([
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['required', 'string'],
            'date'                  => ['required', 'date', 'after_or_equal:today'],
            'time_start'            => ['required', 'date_format:H:i'],
            'time_end'              => ['required', 'date_format:H:i', 'after:time_start'],
            // Main-campus rooms only (POLICY.md § Event Creation: no free text).
            'venue'                 => ['required', 'string', Rule::in(ClubActivity::VENUES)],
            'purpose'               => ['required', 'string'],
            'expected_participants' => ['required', 'integer', 'min:1'],
            'activity_type'         => ['required', 'in:internal_meeting,acle,community_involvement,campus_resource_use,other_external'],
            'target_year_levels'    => ['nullable', 'array'],
            'target_year_levels.*'  => ['integer', 'between:1,4'],
            'approval_letter'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'letter_body'           => ['nullable', 'string', 'max:5000'],
            'letter_ai_drafted'     => ['nullable', 'boolean'],
        ], [
            'title.required'                  => 'Activity Title is required.',
            'description.required'            => 'Description is required.',
            'date.required'                   => 'Date is required.',
            'date.after_or_equal'             => 'The activity date must be today or in the future.',
            'time_start.required'             => 'Start time is required.',
            'time_end.required'               => 'End time is required.',
            'time_end.after'                  => 'End time must be after start time.',
            'venue.required'                  => 'Location is required.',
            'venue.in'                        => 'Choose a room from the main campus list.',
            'purpose.required'                => 'Purpose / Objectives is required.',
            'expected_participants.required'  => 'Expected Participants is required.',
            'activity_type.required'          => 'Activity Type is required.',
        ]);

        // Checked before the venue: there is no point reporting who holds a slot that
        // has already gone by, and the officer has to change the time either way.
        if ($error = $this->pastStartError($validated)) {
            return back()->withInput()->withErrors($error);
        }

        // Venue double-booking is a hard block, checked before the soft duplicate
        // warning below so a real clash is never masked by a bypassable prompt.
        if ($error = $this->venueConflictError($validated)) {
            return back()->withInput()->withErrors($error);
        }

        // Duplicate title+date check (non-blocking — user can bypass with confirmed flag)
        if (! $request->boolean('confirmed')) {
            $duplicate = ClubActivity::where('club_id', $club->id)
                ->where('title', $validated['title'])
                ->whereDate('date', $validated['date'])
                ->exists();

            if ($duplicate) {
                return back()->withInput()->with('duplicate_warning', true);
            }
        }

        // Internal meetings are scheduled outright. Everything else is sent to the VPA with
        // a room booking letter, then to the club adviser (POLICY.md §Event Creation).
        $needsApproval = $validated['activity_type'] !== 'internal_meeting';

        // The officer read this letter on the form (the AI's draft or their own words)
        // before pressing Send; with none written, it is the template, which no AI wrote.
        $letterBody = trim((string) ($validated['letter_body'] ?? ''));

        if ($needsApproval && $letterBody !== '' && ($error = $this->letterVenueError($letterBody, $validated['venue']))) {
            return back()->withInput()->withErrors($error);
        }

        $officer = User::find(auth_user_id());

        $approvalLetterPath = null;
        if ($request->hasFile('approval_letter')) {
            $approvalLetterPath = $request->file('approval_letter')->store('approval-letters', 'local');
        }

        $event = ClubActivity::create([
            'club_id'               => $club->id,
            'title'                 => $validated['title'],
            'description'           => $validated['description'],
            'date'                  => $validated['date'],
            'time_start'            => $validated['time_start'],
            'time_end'              => $validated['time_end'],
            'venue'                 => $validated['venue'],
            'purpose'               => $validated['purpose'],
            'expected_participants' => $validated['expected_participants'],
            'activity_type'         => $validated['activity_type'],
            'target_year_levels'    => $this->normalizeYearLevels($request),
            'approval_status'       => $needsApproval ? 'pending_vpa' : 'no_approval_needed',
            'approval_letter_path'  => $approvalLetterPath,
            'status'                => 'scheduled',
            'created_by'            => auth_user_id(),
            'letter_body'           => $needsApproval ? ($letterBody !== '' ? $letterBody : BookingLetter::templateBody($club->name, $validated)) : null,
            'letter_ai_drafted'     => $needsApproval && $letterBody !== '' && $request->boolean('letter_ai_drafted'),
            // The officer's signature goes on as they send it.
            'letter_signatures'     => $needsApproval && $officer
                ? ['officer' => $this->letters->sign($officer, BookingLetter::officerPosition($club, $officer))]
                : null,
        ]);

        if ($needsApproval) {
            AuditLog::record('activity.letter.sent', $event, ['club_id' => $club->id, 'venue' => $event->venue, 'ai_drafted' => $event->letter_ai_drafted]);

            NotifyVpaOfBookingLetter::dispatch($event->id);
        } else {
            // Already confirmed, so members can be told straight away. Activities
            // awaiting the adviser's sign-off announce themselves on approval instead.
            NotifyMembersOfActivity::dispatch($event->id, NotifyMembersOfActivity::SCHEDULED, auth_user_id());
        }

        // Mirror to Firebase RTDB (eventual consistency — do not roll back on failure)
        // TODO: remove SQLite guard / revisit Firebase node naming before Supabase migration
        try {
            $this->firebase->writeEvent($club->id, $event->id, [
                'title'      => $event->title,
                'date'       => $event->date->toDateString(),
                'time_start' => $event->time_start,
                'time_end'   => $event->time_end,
                'venue'      => $event->venue,
                'status'     => $event->status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Firebase activity mirror failed for activity #' . $event->id . ': ' . $e->getMessage());
        }

        // Confirmed activities (internal meetings) go onto members' Google Calendars in the background.
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::ACTIVITY, $event->id);

        if ($needsApproval) {
            return redirect()->route('officer.activities.show', $event)
                ->with('success', 'Activity saved. Your room booking letter was signed and sent to the Vice President for Administration.');
        }

        return redirect()->route('officer.activities.index')
            ->with('success', 'Activity successfully scheduled!');
    }

    /**
     * Draft the body of the room booking letter from what the officer has filled in.
     *
     * Gemini writes it when it can; otherwise the template does, from the same fields, so
     * the officer always gets a letter to start from (POLICY.md §AI & Automation). The
     * draft goes back to the form only: nothing is saved or sent until the officer reads
     * it and presses Send.
     */
    public function letterDraft(Request $request): \Illuminate\Http\JsonResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return response()->json(['error' => 'You are not assigned to any club.'], 403);
        }

        $this->normalizeTimes($request);

        $validated = $request->validate([
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'date'                  => ['required', 'date'],
            'time_start'            => ['required', 'date_format:H:i'],
            'time_end'              => ['required', 'date_format:H:i', 'after:time_start'],
            'venue'                 => ['required', 'string', 'max:255'],
            'purpose'               => ['nullable', 'string', 'max:2000'],
            'expected_participants' => ['nullable', 'integer', 'min:1'],
            'activity_type'         => ['nullable', 'string', Rule::in(array_keys(ClubActivity::TYPE_LABELS))],
            'target_year_levels'    => ['nullable', 'array'],
            'target_year_levels.*'  => ['integer', 'between:1,4'],
        ], [
            'title.required'      => 'Fill in the activity title first.',
            'date.required'       => 'Pick the date first.',
            'time_start.required' => 'Set the start time first.',
            'time_end.required'   => 'Set the end time first.',
            'time_end.after'      => 'End time must be after start time.',
            'venue.required'      => 'Choose the room first.',
        ]);

        $levels = $this->normalizeYearLevels($request);

        $body = $this->gemini->draftBookingLetter([
            'club'         => $club->name,
            'title'        => $validated['title'],
            'type'         => ClubActivity::TYPE_LABELS[$validated['activity_type'] ?? ''] ?? 'Club activity',
            'date'         => Carbon::parse($validated['date'])->format('l, F j, Y'),
            'time'         => Carbon::parse($validated['time_start'])->format('g:i A') . ' to ' . Carbon::parse($validated['time_end'])->format('g:i A'),
            'venue'        => $validated['venue'],
            'description'  => $validated['description'] ?? '',
            'purpose'      => $validated['purpose'] ?? '',
            'participants' => $validated['expected_participants'] ?? null,
            'year_levels'  => $levels ? collect($levels)->map(fn (int $level) => year_level_label($level))->implode(', ') : 'All year levels',
        ]);

        if ($body !== null) {
            AuditLog::record('ai.booking_letter.drafted', null, ['club_id' => $club->id]);
        }

        return response()->json([
            'body'   => $body ?? BookingLetter::templateBody($club->name, [
                'title'                 => $validated['title'],
                'date'                  => $validated['date'],
                'time_start'            => $validated['time_start'],
                'time_end'              => $validated['time_end'],
                'venue'                 => $validated['venue'],
                'purpose'               => $validated['purpose'] ?? '',
                'expected_participants' => $validated['expected_participants'] ?? 0,
            ]),
            'ai'     => $body !== null,
            'notice' => $body !== null
                ? 'AI-assisted draft — read it and change anything that is not right before you send it.'
                : 'AI Unavailable — Manual Draft Required. The letter was written from the template; read it and change it as needed before you send it.',
        ]);
    }

    /** The activity's room booking letter as a PDF, with every signature given so far. */
    public function bookingLetter(ClubActivity $event)
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id || ! $event->hasBookingLetter()) {
            abort(404);
        }

        return $this->letters->pdf($event)->download($this->letters->filename($event));
    }

    /**
     * AI-assisted activity proposal.
     *
     * Two independent halves, deliberately kept separate:
     *
     * 1. The venue availability check, which always runs and is authoritative. It
     *    is the gate the officer must clear before the proposal can be submitted,
     *    and it is the same check `store()` enforces server-side — so a proposal
     *    reported as blocked cannot be pushed through by ignoring this endpoint.
     * 2. The Gemini draft, which is best-effort: the description, the purpose, a
     *    turnout estimate drawn from the club's own roster and the year levels the
     *    activity looks aimed at. When AI is off or fails, the availability answer
     *    still stands and the officer fills the form in themselves (POLICY.md:
     *    "keep manual alternatives fully functional").
     *
     * Nothing here creates an activity. The draft goes back to the form for the
     * officer to review and submit — that review is the human gate POLICY requires.
     */
    public function aiProposal(Request $request): \Illuminate\Http\JsonResponse
    {
        $club = $this->officerClub();

        if (! $club) {
            return response()->json(['error' => 'You are not assigned to any club.'], 403);
        }

        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'date'       => ['required', 'date', 'after_or_equal:today'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end'   => ['required', 'date_format:H:i', 'after:time_start'],
            'venue'      => ['required', 'string', Rule::in(ClubActivity::VENUES)],
            'context'    => ['nullable', 'string', 'max:500'],
        ], [
            'venue.in' => 'Choose a room from the main campus list.',
        ]);

        // Told here too, so the officer finds out at "Check venue & draft proposal"
        // rather than after filling in the rest of the form. Same wording as store().
        if ($error = $this->pastStartError($validated)) {
            throw ValidationException::withMessages($error);
        }

        // ── 1. Availability (always) ─────────────────────────────────────────
        $conflict = ClubActivity::findVenueConflict(
            $validated['venue'],
            $validated['date'],
            $validated['time_start'],
            $validated['time_end'],
        );

        $available = $conflict === null;

        $conflictPayload = null;

        if ($conflict) {
            // Same cross-club rule as store(): another club's activity title is
            // withheld, its occupied window is not.
            $ours = $conflict->club_id === $club->id;

            $conflictPayload = [
                'time_start' => substr((string) $conflict->time_start, 0, 5),
                'time_end'   => substr((string) $conflict->time_end, 0, 5),
                'own_club'   => $ours,
                'title'      => $ours ? $conflict->title : null,
                'message'    => $this->venueConflictError($validated)['venue'],
            ];
        }

        // ── 2. Draft (best-effort) ───────────────────────────────────────────
        $aiAvailable = $this->gemini->isReady();
        $roster = $aiAvailable ? $this->rosterFacts($club) : null;
        $draft  = null;

        if ($aiAvailable) {
            $draft = $this->gemini->draftActivityProposal(
                $validated['title'],
                $club->name,
                $validated['venue'],
                $validated['date'],
                $validated['time_start'],
                $validated['time_end'],
                $validated['context'] ?? '',
                $roster,
            );
        }

        return response()->json([
            'venue_available'    => $available,
            'can_submit'         => $available,
            'conflict'           => $conflictPayload,
            'alternative_venues' => $available ? [] : ClubActivity::venuesFreeAt(
                $validated['date'],
                $validated['time_start'],
                $validated['time_end'],
                $validated['venue'],
            ),
            'ai_available' => $aiAvailable,
            'draft'        => $draft,
            // The figure behind the turnout estimate, so the form can say where the
            // number came from instead of presenting it as one the system knows.
            'roster_total' => $roster['total'] ?? null,
            'notice'       => $aiAvailable && $draft
                ? 'AI-assisted draft — review and edit before submitting.'
                : 'AI Unavailable — Manual Draft Required.',
        ]);
    }

    public function show(ClubActivity $event): View
    {
        // Verify the activity belongs to one of the officer's clubs
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        $event->load('club', 'creator', 'vpaReviewer', 'approvedBy', 'changeLogs.changedBy', 'photos.uploader');

        $attendanceCount = $event->attendance()->whereNotNull('time_in')->count();

        // Photos and records for the semester report; fixed once that report reached the DSA.
        $photosByCategory = $event->photos->groupBy('category');
        $reportLocked     = $event->submittedReport() !== null;

        return view('officer.activities.show', compact('event', 'club', 'attendanceCount', 'photosByCategory', 'reportLocked'));
    }

    public function edit(ClubActivity $event): View
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        $venues = ClubActivity::VENUES;
        $yearLevels = self::YEAR_LEVELS;

        return view('officer.activities.edit', compact('event', 'club', 'venues', 'yearLevels'));
    }

    public function update(Request $request, ClubActivity $event): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        $this->normalizeTimes($request);

        $validated = $request->validate([
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['required', 'string'],
            'date'                  => ['required', 'date'],
            'time_start'            => ['required', 'date_format:H:i'],
            'time_end'              => ['required', 'date_format:H:i', 'after:time_start'],
            // A main-campus room, or the venue the activity already has: an activity
            // booked before the room list changed stays editable without being moved.
            'venue'                 => ['required', 'string', Rule::in([...ClubActivity::VENUES, $event->venue])],
            'purpose'               => ['required', 'string'],
            'expected_participants' => ['required', 'integer', 'min:1'],
            'activity_type'         => ['required', 'in:internal_meeting,acle,community_involvement,campus_resource_use,other_external'],
            'target_year_levels'    => ['nullable', 'array'],
            'target_year_levels.*'  => ['integer', 'between:1,4'],
            'approval_letter'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'letter_body'           => ['nullable', 'string', 'max:5000'],
            'letter_ai_drafted'     => ['nullable', 'boolean'],
        ], [
            'title.required'                  => 'Activity Title is required.',
            'description.required'            => 'Description is required.',
            'date.required'                   => 'Date is required.',
            'time_start.required'             => 'Start time is required.',
            'time_end.required'               => 'End time is required.',
            'time_end.after'                  => 'End time must be after start time.',
            'venue.required'                  => 'Location is required.',
            'venue.in'                        => 'Choose a room from the main campus list.',
            'purpose.required'                => 'Purpose / Objectives is required.',
            'expected_participants.required'  => 'Expected Participants is required.',
            'activity_type.required'          => 'Activity Type is required.',
        ]);

        // Same hard block as on create, ignoring this activity's own booking so an
        // edit that leaves the slot untouched does not collide with itself.
        if ($error = $this->venueConflictError($validated, $event)) {
            return back()->withInput()->withErrors($error);
        }

        // Checkboxes are absent from the request when none are ticked, so set this
        // explicitly rather than relying on the validated array (which would omit it).
        $validated['target_year_levels'] = $this->normalizeYearLevels($request);

        $letterBody = trim((string) ($validated['letter_body'] ?? ''));
        unset($validated['letter_body'], $validated['letter_ai_drafted']);

        $before = $event->only([
            'title', 'description', 'date', 'time_start', 'time_end',
            'venue', 'purpose', 'expected_participants', 'activity_type', 'approval_status',
        ]);
        $before['date'] = $event->date->toDateString();

        // The fields the officer actually changed. Times compare at H:i and the date as a
        // date, because the stored values read back as "07:10:00" and a Carbon.
        $edited = array_values(array_filter(
            ['title', 'description', 'date', 'time_start', 'time_end', 'venue', 'purpose', 'expected_participants', 'activity_type'],
            fn (string $key) => match ($key) {
                'date'                   => Carbon::parse($validated['date'])->toDateString() !== $before['date'],
                'time_start', 'time_end' => substr((string) $validated[$key], 0, 5) !== substr((string) $before[$key], 0, 5),
                default                  => (string) $validated[$key] !== (string) $before[$key],
            },
        ));

        $needsApproval = $validated['activity_type'] !== 'internal_meeting';
        $letterEdited  = $needsApproval && $letterBody !== '' && $letterBody !== (string) $event->letter_body;
        $changed       = $edited || $letterEdited || $request->hasFile('approval_letter')
            || $validated['target_year_levels'] != ($event->target_year_levels ?: null);

        // POLICY.md §Event Creation: a changed activity that needs approval goes back to
        // the VPA with a re-signed letter, whatever stage it had reached: approved, with
        // the adviser, or returned (which is how a returned letter is sent again). So does
        // an internal meeting turned into an activity that needs approval.
        $resubmit = $needsApproval && ($event->approval_status === 'no_approval_needed' || $changed);

        if ($resubmit) {
            $material = array_intersect(['title', 'date', 'time_start', 'time_end', 'venue'], $edited);

            // A letter still describing the old title, date, time or room is not sent as it
            // is: the officer drafts it again, or rewrites it themselves.
            if ($material && $event->letter_body !== null && ! $letterEdited) {
                $what = implode(', ', array_map(fn (string $field) => str_replace('_', ' ', $field), $material));

                return back()->withInput()->withErrors([
                    'letter_body' => "You changed the {$what}, but the letter still describes the activity as it was. Draft it again, or edit it to match, before sending.",
                ]);
            }

            $letterBody = $letterBody !== '' ? $letterBody : ($event->letter_body ?? BookingLetter::templateBody($club->name, $validated));

            if ($error = $this->letterVenueError($letterBody, $validated['venue'])) {
                return back()->withInput()->withErrors($error);
            }
        }

        if ($request->hasFile('approval_letter')) {
            $validated['approval_letter_path'] = $request->file('approval_letter')->store('approval-letters', 'local');
        }

        $wasWithVpa = $event->approval_status === 'pending_vpa';

        if (! $needsApproval) {
            $validated['approval_status'] = 'no_approval_needed';
        } elseif ($resubmit) {
            $officer = User::find(auth_user_id());

            $validated += [
                'approval_status'   => 'pending_vpa',
                'review_remarks'    => null,
                'approved_by'       => null,
                'approved_at'       => null,
                'vpa_decision'      => null,
                'vpa_reviewed_by'   => null,
                'vpa_reviewed_at'   => null,
                'letter_body'       => $letterBody,
                'letter_ai_drafted' => $letterEdited ? $request->boolean('letter_ai_drafted') : (bool) $event->letter_ai_drafted,
                // Signed again by whoever sends it this time; the VPA and adviser sign afresh.
                'letter_signatures' => $officer ? ['officer' => $this->letters->sign($officer, BookingLetter::officerPosition($club, $officer))] : null,
            ];
        }

        $event->update($validated);

        $after = $event->only([
            'title', 'description', 'date', 'time_start', 'time_end',
            'venue', 'purpose', 'expected_participants', 'activity_type', 'approval_status',
        ]);
        $after['date'] = $event->date->toDateString();

        // Compare times at H:i: the stored value reads back as "07:10:00" while the
        // saved input is "07:10", which would otherwise log (and announce) a time
        // change on every edit.
        foreach (['time_start', 'time_end'] as $field) {
            $before[$field] = substr((string) $before[$field], 0, 5);
            $after[$field]  = substr((string) $after[$field], 0, 5);
        }

        $changes = array_filter(
            array_map(
                fn ($key) => $before[$key] != $after[$key] ? ['field' => $key, 'before' => $before[$key], 'after' => $after[$key]] : null,
                array_keys($before)
            )
        );

        if (! empty($changes)) {
            ActivityChangeLog::create([
                'club_activity_id' => $event->id,
                'changed_by'       => auth_user_id(),
                'changes'          => array_values($changes),
            ]);
        }

        if ($resubmit) {
            AuditLog::record('activity.letter.sent', $event, ['club_id' => $club->id, 'venue' => $event->venue, 'resent' => true]);

            // Already in the VPA's queue: they see the new letter there, with no second notice.
            if (! $wasWithVpa) {
                NotifyVpaOfBookingLetter::dispatch($event->id);
            }
        }

        // Tell members only when something they would actually turn up for moved.
        // A reworded description is not worth a notification; a new date, time or
        // venue is. An edit that reset the activity to pending is not announced
        // either — the job re-checks confirmation and will hold it until approval.
        $material = ['title', 'date', 'time_start', 'time_end', 'venue'];

        if (array_intersect($material, array_column($changes, 'field'))) {
            NotifyMembersOfActivity::dispatch($event->id, NotifyMembersOfActivity::UPDATED, auth_user_id());
        }

        // Re-sync members' Google Calendars: updates the event, or removes it if the
        // edit dropped it back to pending approval or retargeted the year levels.
        SyncToGoogleCalendars::dispatch(GoogleCalendarEvent::ACTIVITY, $event->id);

        return redirect()->route('officer.activities.show', $event)
            ->with('success', 'Activity updated.' . ($resubmit
                ? ' The room booking letter was signed again and sent to the Vice President for Administration, so it needs their approval and the adviser\'s again.'
                : ''));
    }

    public function downloadLetter(ClubActivity $event)
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id || ! $event->approval_letter_path) {
            abort(404);
        }

        return Storage::disk('local')->download($event->approval_letter_path);
    }

    public function complete(Request $request, ClubActivity $event): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        if ($event->status !== 'scheduled') {
            return back()->withErrors(['status' => 'This activity has already been completed.']);
        }

        $event->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // The attendance is concluded: everyone checked in is told what their record says.
        // Queued first, so the members are not kept waiting behind the AI recap below.
        NotifyMemberOfAttendance::dispatch($event->id, NotifyMemberOfAttendance::CLOSED, null, auth_user_id());

        // Milestone reached — draft a post-event narrative for the bulletin.
        // The draft is held for adviser review; nothing is published automatically.
        GenerateClubNarrativeJob::dispatch($event->id);

        return redirect()->route('officer.activities.show', $event)
            ->with('success', 'Activity marked as completed.');
    }
}

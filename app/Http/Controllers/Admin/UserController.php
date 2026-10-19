<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncClubMemberGoogleCalendar;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manage Users: an administrator changes a user's role, and links advisers to clubs
 * (POLICY.md §Role-Based Access).
 *
 * - An officer position is always held in one named club. A student in PSITS, BYTE and
 *   Debate who is made Secretary is made Secretary of the one club the administrator
 *   picks, among the clubs they are an active member of. A student holds one post at a
 *   time, so the officer pages never have to guess which club is meant.
 * - Adviser accounts belong to employees: a student account never becomes one, and an
 *   adviser account never becomes a member or officer.
 * - A club has one adviser. An adviser who registered asked for a club with none; the
 *   administrator confirms that request here, or declines it.
 */
class UserController extends Controller
{
    /** Staff roles an administrator gives. */
    private const STAFF_ROLES = ['adviser', 'dsa', 'vpa'];

    /**
     * Every role an administrator gives: member, each officer position, and the staff
     * roles. The old single `mmo` post is kept by those who hold it but not given.
     */
    private function assignableRoles(): array
    {
        return ['member', ...array_keys(User::POSITION_LABELS), ...self::STAFF_ROLES];
    }

    public function index(Request $request): View
    {
        $search = $request->query('search');
        $filter = $request->query('filter') === 'club-requests' ? 'club-requests' : null;

        $users = User::with([
                'department', 'course', 'requestedClub', 'advisedClub',
                'clubMemberships' => fn ($q) => $q->where('status', 'active')->with('club:id,name,acronym'),
                'officerPosts.club:id,name,acronym',
            ])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($search).'%']);
            }))
            ->when($filter, fn ($q) => $q->whereNotNull('requested_club_id'))
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        // Who holds each post in the clubs on this page, so the confirmation can name
        // anyone an appointment would replace.
        $clubIds = $users->getCollection()->flatMap(fn (User $u) => $u->clubMemberships->pluck('club_id'))->unique()->values();

        $holders = ClubOfficer::whereIn('club_id', $clubIds)->with('user:id,first_name,last_name')->get()
            ->groupBy('club_id')
            ->map(fn ($posts) => $posts->mapWithKeys(fn (ClubOfficer $post) => [
                $post->position => ['id' => $post->user_id, 'name' => $post->user?->name],
            ]));

        return view('admin.users.index', [
            'users'        => $users,
            'search'       => $search,
            'filter'       => $filter,
            'positions'    => User::POSITION_LABELS,
            'holders'      => $holders,
            // Clubs an adviser can be given: active, and with no adviser yet.
            'openClubs'    => Club::where('is_active', true)->whereNull('adviser_id')->orderBy('name')->get(['id', 'name', 'acronym']),
            'requestCount' => User::whereNotNull('requested_club_id')->count(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role'    => ['required', Rule::in($this->assignableRoles())],
            'club_id' => [Rule::requiredIf(fn () => User::isOfficerRole($request->input('role'))), 'nullable', 'integer'],
        ], [
            'club_id.required' => 'Choose the club this position is held in.',
        ]);

        if ($user->id === auth_user_id()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $oldRole = $user->role;
        $newRole = $validated['role'];

        // Adviser accounts are for employees, never students — and the other way round.
        if ($newRole === 'adviser' && $user->isStudentAccount()) {
            return back()->with('error', "{$user->name} has a student account, so it cannot be made an adviser. "
                . 'Advisers create their own account on the adviser sign-up page, with their adviser number.');
        }

        if (in_array($newRole, User::STUDENT_ROLES, true) && $user->isEmployeeAccount()) {
            return back()->with('error', "{$user->name} has an adviser (employee) account, so it cannot be made a member or a club officer.");
        }

        $club = null;

        if (User::isOfficerRole($newRole)) {
            $club = ClubMember::where('user_id', $user->id)
                ->where('club_id', $validated['club_id'])
                ->where('status', 'active')
                ->with('club')
                ->first()
                ?->club;

            if (! $club) {
                return back()->with('error', "{$user->name} is not an active member of that club. A position can only be held in a club the student belongs to.");
            }
        }

        $posts = ClubOfficer::where('user_id', $user->id)->with('club')->get();

        $unchanged = $oldRole === $newRole && ($club
            ? $posts->count() === 1 && $posts->first()->club_id === $club->id && $posts->first()->position === $newRole
            : $posts->isEmpty());

        if ($unchanged) {
            return back()->with('info', 'No change — ' . $user->name . ' is already ' . User::roleLabel($newRole) . ($club ? " of {$club->name}" : '') . '.');
        }

        $advisedClub = $oldRole === 'adviser' && $newRole !== 'adviser' ? Club::where('adviser_id', $user->id)->first() : null;

        $replaced = DB::transaction(function () use ($user, $newRole, $club, $posts, $advisedClub) {
            // One post at a time: the one held before, in whatever club, is given up.
            foreach ($posts as $post) {
                $post->delete();
                $this->setClubTitle($post->club_id, $user->id, 'Member');
            }

            $replaced = null;

            if ($club) {
                // A club has one of each post. Whoever holds this one hands it over.
                $holder = ClubOfficer::where('club_id', $club->id)
                    ->where('position', $newRole)
                    ->where('user_id', '!=', $user->id)
                    ->with('user')
                    ->first();

                if ($holder) {
                    $holder->delete();
                    $this->setClubTitle($club->id, $holder->user_id, 'Member');

                    // An officer of no other club returns to Member, as when a club is deleted.
                    if ($holder->user && User::isOfficerRole($holder->user->role) && ! ClubOfficer::where('user_id', $holder->user_id)->exists()) {
                        $holder->user->update(['role' => 'member']);
                    }

                    $replaced = $holder->user;
                }

                ClubOfficer::create([
                    'club_id'     => $club->id,
                    'user_id'     => $user->id,
                    'position'    => $newRole,
                    'assigned_at' => now(),
                ]);

                $this->setClubTitle($club->id, $user->id, User::roleLabel($newRole));
            }

            // An adviser who stops being one leaves their club with no adviser.
            $advisedClub?->update(['adviser_id' => null]);

            $user->update(['role' => $newRole] + ($newRole === 'adviser' ? [] : ['requested_club_id' => null]));

            return $replaced;
        });

        if ($advisedClub) {
            SyncClubMemberGoogleCalendar::dispatch($user->id, $advisedClub->id);
        }

        AuditLog::record('admin.user.role_changed', $user, array_filter([
            'target_email'     => $user->email,
            'old_role'         => $oldRole,
            'new_role'         => $newRole,
            'club_id'          => $club?->id,
            'previous_posts'   => $posts->map(fn (ClubOfficer $p) => ['club_id' => $p->club_id, 'position' => $p->position])->all() ?: null,
            'replaced_user_id' => $replaced?->id,
            'left_club_id'     => $advisedClub?->id,
        ], fn ($value) => $value !== null));

        return back()->with('success', $this->roleChangedMessage($user, $oldRole, $newRole, $club, $posts, $replaced, $advisedClub));
    }

    /** Link an adviser to the club they advise, move them to another, or take them off theirs (an empty club_id). */
    public function assignClub(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'adviser') {
            return back()->with('error', 'Only an adviser account can be given a club to advise.');
        }

        $validated = $request->validate([
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
        ]);

        $current = Club::where('adviser_id', $user->id)->first();
        $club    = isset($validated['club_id']) ? Club::find($validated['club_id']) : null;

        if ($club?->id === $current?->id) {
            return back()->with('info', $club
                ? "No change — {$user->name} is already the adviser of {$club->name}."
                : "No change — {$user->name} does not advise a club.");
        }

        // One adviser per club, never more: a club that has one is not given a second.
        if ($club && $club->adviser_id !== null) {
            return back()->with('error', "{$club->name} already has an adviser, {$club->adviserUser?->name}. "
                . 'A club has one adviser: take them off the club first.');
        }

        $requestedId = $user->requested_club_id;

        $taken = DB::transaction(function () use ($user, $club, $current) {
            // Claimed only while the club still has no adviser, so two administrators
            // confirming at once cannot give it two. First, so a lost race changes nothing.
            if ($club && Club::whereKey($club->id)->whereNull('adviser_id')->update(['adviser_id' => $user->id]) === 0) {
                return true;
            }

            // One club per adviser: moving to another club leaves the old one.
            $current?->update(['adviser_id' => null]);

            $user->update(['requested_club_id' => null]);

            return false;
        });

        if ($taken) {
            return back()->with('error', "{$club->name} was just given another adviser. Pick a club that has none.");
        }

        foreach (array_unique(array_filter([$current?->id, $club?->id])) as $clubId) {
            SyncClubMemberGoogleCalendar::dispatch($user->id, $clubId);
        }

        AuditLog::record('admin.adviser.club_assigned', $user, array_filter([
            'club_id'           => $club?->id,
            'previous_club_id'  => $current?->id,
            'requested_club_id' => $requestedId,
        ], fn ($value) => $value !== null));

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => $club?->id,
            'title'        => $club ? 'You are now a club adviser' : 'Removed as club adviser',
            'body'         => $club
                ? "An administrator made you the adviser of {$club->name}."
                : "An administrator took you off {$current?->name} as its adviser.",
            'action_url'   => route('adviser.dashboard'),
        ]);

        return back()->with('success', $club
            ? "{$user->name} is now the adviser of {$club->name}." . ($current && $current->id !== $club->id ? " {$current->name} has no adviser now." : '')
            : "{$user->name} no longer advises {$current?->name}, which has no adviser now.");
    }

    /** Turn down the club an adviser asked for when they registered. */
    public function declineClubRequest(User $user): RedirectResponse
    {
        $club = $user->requestedClub;

        if (! $club) {
            return back()->with('info', "{$user->name} has no club request waiting.");
        }

        $user->update(['requested_club_id' => null]);

        AuditLog::record('admin.adviser.club_request_declined', $user, ['requested_club_id' => $club->id]);

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => $club->id,
            'title'        => 'Club request declined',
            'body'         => "An administrator did not confirm you as the adviser of {$club->name}. Please contact the Student Affairs Office.",
            'action_url'   => route('adviser.dashboard'),
        ]);

        return back()->with('success', "Declined {$user->name}'s request to advise {$club->name}.");
    }

    /** The title a member carries in one club's member list: "Member", or the post they hold there. */
    private function setClubTitle(int $clubId, int $userId, string $title): void
    {
        ClubMember::where('club_id', $clubId)->where('user_id', $userId)->update(['role' => $title]);
    }

    private function roleChangedMessage(User $user, string $oldRole, string $newRole, ?Club $club, $posts, ?User $replaced, ?Club $leftClub): string
    {
        $message = $club
            ? "{$user->name} is now " . User::roleLabel($newRole) . " of {$club->name}."
            : "Role for {$user->name} changed from \"" . User::roleLabel($oldRole) . '" to "' . User::roleLabel($newRole) . '".';

        foreach ($posts as $post) {
            if (! $club || $post->club_id !== $club->id) {
                $message .= ' They are no longer ' . User::roleLabel($post->position) . " of {$post->club?->name}.";
            }
        }

        if ($replaced) {
            $message .= " {$replaced->name} held that post and is now a Member of the club.";
        }

        if ($leftClub) {
            $message .= " {$leftClub->name} has no adviser now.";
        }

        return $message . ' The new role applies the next time they sign in.';
    }
}

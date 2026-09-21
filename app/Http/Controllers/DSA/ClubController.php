<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Jobs\SyncClubMemberGoogleCalendar;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\College;
use App\Models\User;
use App\Rules\FacebookPageUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $type   = $request->query('type');   // 'academic' | 'non_academic' | null
        $status = $request->query('status'); // 'compliant' | 'non_compliant' | 'pending' | null

        $clubs = Club::query()
            ->when($type === 'academic',     fn ($q) => $q->academic())
            ->when($type === 'non_academic', fn ($q) => $q->nonAcademic())
            ->when($status === 'compliant',     fn ($q) => $q->compliant())
            ->when($status === 'non_compliant', fn ($q) => $q->nonCompliant())
            ->when($status === 'pending',       fn ($q) => $q->pendingApplications())
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
                ->orWhereRaw('LOWER(acronym) LIKE ?', ['%'.strtolower($search).'%'])
            ))
            ->withCount([
                'members as active_member_count'      => fn ($q) => $q->where('status', 'active'),
                'members as pending_applications_count' => fn ($q) => $q->where('registration_status', 'pending'),
                'chedReports as finalized_reports_count' => fn ($q) => $q->where('is_finalized', true),
            ])
            ->with('adviserUser', 'college')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Sub-tab counts (respect the type filter so the tabs stay consistent).
        $base = fn () => Club::query()
            ->when($type === 'academic',     fn ($q) => $q->academic())
            ->when($type === 'non_academic', fn ($q) => $q->nonAcademic());

        $counts = [
            'all'           => $base()->count(),
            'compliant'     => $base()->compliant()->count(),
            'non_compliant' => $base()->nonCompliant()->count(),
            'pending'       => $base()->pendingApplications()->count(),
        ];

        return view('dsa.clubs.index', compact('clubs', 'search', 'type', 'status', 'counts'));
    }

    public function create(): View
    {
        $colleges  = College::orderBy('name')->get();
        $advisers  = User::where('role', 'adviser')->orderBy('first_name')->get();

        return view('dsa.clubs.create', compact('colleges', 'advisers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'acronym'     => ['required', 'string', 'max:50'],
            'type'        => ['required', 'in:academic,non_academic'],
            'college_id'  => ['nullable', 'exists:colleges,id'],
            'adviser_id'  => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'facebook_url' => ['nullable', 'string', 'max:255', new FacebookPageUrl],
            'logo'        => ['nullable', 'image', 'max:2048'],
        ]);

        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $path    = $request->file('logo')->store('clubs/logos', 'public');
            $logoUrl = Storage::url($path);
        }

        $club = Club::create([
            'name'             => $validated['name'],
            'acronym'          => $validated['acronym'],
            'slug'             => Str::slug($validated['name']) . '-' . Str::random(4),
            'type'             => $validated['type'],
            'club_type'        => $validated['type'] === 'academic' ? 'Academic' : 'Non-Academic',
            'college_id'       => $validated['college_id'] ?? null,
            'adviser_id'       => $validated['adviser_id'] ?? null,
            'description'      => $validated['description'] ?? null,
            'facebook_url'     => $validated['facebook_url'] ?? null,
            'profile_photo_url' => $logoUrl,
            'is_active'        => true,
        ]);

        AuditLog::record('club.created', $club, ['name' => $club->name]);

        return redirect()->route('dsa.clubs.index')
            ->with('success', 'Club created successfully.');
    }

    public function show(Club $club): View
    {
        $club->load('officers.user', 'adviserUser', 'college');

        $activeMembers = $club->members()
            ->where('status', 'active')
            ->with('user')
            ->paginate(10);

        $recentEvents = $club->activities()
            ->orderByDesc('date')
            ->take(5)
            ->get();

        $stats = [
            'members'    => $club->members()->where('status', 'active')->count(),
            'activities' => $club->activities()->count(),
            'officers' => $club->officers()->count(),
            'pending'  => $club->members()->where('status', 'pending')->count(),
        ];

        return view('dsa.clubs.show', compact('club', 'activeMembers', 'recentEvents', 'stats'));
    }

    public function edit(Club $club): View
    {
        $colleges = College::orderBy('name')->get();
        $advisers = User::where('role', 'adviser')->orderBy('first_name')->get();

        return view('dsa.clubs.edit', compact('club', 'colleges', 'advisers'));
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'acronym'     => ['required', 'string', 'max:50'],
            'type'        => ['required', 'in:academic,non_academic'],
            'college_id'  => ['nullable', 'exists:colleges,id'],
            'adviser_id'  => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'facebook_url' => ['nullable', 'string', 'max:255', new FacebookPageUrl],
            'logo'        => ['nullable', 'image', 'max:2048'],
            'banner'      => ['nullable', 'image', 'max:4096'],
            'is_active'   => ['boolean'],
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if it was stored locally
            if ($club->profile_photo_url && Str::startsWith($club->profile_photo_url, '/storage/')) {
                Storage::disk('public')->delete(Str::after($club->profile_photo_url, '/storage/'));
            }
            $path = $request->file('logo')->store('clubs/logos', 'public');
            $validated['profile_photo_url'] = Storage::url($path);
        }

        if ($request->hasFile('banner')) {
            if ($club->banner_image && Str::startsWith($club->banner_image, '/storage/')) {
                Storage::disk('public')->delete(Str::after($club->banner_image, '/storage/'));
            }
            $validated['banner_image'] = Storage::url($request->file('banner')->store('clubs/banners', 'public'));
        }

        $previousAdviserId = $club->adviser_id;

        $club->update([
            'name'             => $validated['name'],
            'acronym'          => $validated['acronym'],
            'type'             => $validated['type'],
            'club_type'        => $validated['type'] === 'academic' ? 'Academic' : 'Non-Academic',
            'college_id'       => $validated['college_id'] ?? null,
            'adviser_id'       => $validated['adviser_id'] ?? null,
            'description'      => $validated['description'] ?? null,
            // Absent (another caller) keeps the link; submitted empty clears it.
            'facebook_url'     => $request->exists('facebook_url') ? ($validated['facebook_url'] ?? null) : $club->facebook_url,
            'is_active'        => $request->boolean('is_active', true),
            'profile_photo_url' => $validated['profile_photo_url'] ?? $club->profile_photo_url,
            'banner_image'      => $validated['banner_image'] ?? $club->banner_image,
        ]);

        $changed = array_values(array_diff(array_keys($club->getChanges()), ['updated_at']));
        if ($changed) {
            AuditLog::record('club.updated', $club, ['changed' => $changed]);
        }

        // A new adviser gets the club's upcoming activities on their Google Calendar;
        // the one they replaced has them taken off.
        if ((int) $previousAdviserId !== (int) $club->adviser_id) {
            foreach (array_filter([$previousAdviserId, $club->adviser_id]) as $adviserId) {
                SyncClubMemberGoogleCalendar::dispatch((int) $adviserId, $club->id);
            }
        }

        return redirect()->route('dsa.clubs.show', $club)
            ->with('success', 'Club updated successfully.');
    }
}

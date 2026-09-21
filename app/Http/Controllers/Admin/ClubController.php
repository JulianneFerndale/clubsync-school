<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function academic(Request $request): View
    {
        $search = $request->query('search');

        $clubs = Club::with('department')
            ->where('club_type', 'Academic')
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(acronym) LIKE ?', ['%'.strtolower($search).'%']);
            }))
            ->get()
            ->groupBy('department_slug');

        $departments = Department::whereIn('slug', $clubs->keys())->get()->keyBy('slug');

        return view('admin.clubs.academic', compact('clubs', 'departments', 'search'));
    }

    public function nonAcademic(Request $request): View
    {
        $search = $request->query('search');

        $clubs = Club::where('club_type', 'Non-Academic')
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
                  ->orWhereRaw('LOWER(acronym) LIKE ?', ['%'.strtolower($search).'%']);
            }))
            ->get();

        return view('admin.clubs.non-academic', compact('clubs', 'search'));
    }

    public function create(): View
    {
        $departments = Department::with('courses')->orderBy('short_name')->get();

        return view('admin.clubs.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'acronym'          => ['required', 'string', 'max:50'],
            'slug'             => ['required', 'string', 'unique:clubs,slug'],
            'club_type'        => ['required', 'in:Academic,Non-Academic'],
            'department_slug'  => ['required', 'string'],
            'course_slug'      => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'adviser'          => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['club_type'] === 'Academic') {
            $dept = Department::where('slug', $validated['department_slug'])->firstOrFail();
            Course::where('slug', $validated['course_slug'])
                  ->where('department_id', $dept->id)
                  ->firstOrFail();
        }

        Club::create($validated);

        return redirect()->route('admin.dashboard')->with('success', 'Club created successfully.');
    }
}

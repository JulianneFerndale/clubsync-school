<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyMemberOfAttendance;
use App\Models\Attendance;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    private function officerClub(): ?\App\Models\Club
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->first()
            ?->club;
    }

    public function index(ClubActivity $event): View
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        // Active members of the club
        $memberIds = ClubMember::where('club_id', $club->id)
            ->where('status', 'active')
            ->pluck('user_id');

        $members = User::whereIn('id', $memberIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Attendance records for this activity, keyed by user_id
        $attendance = Attendance::where('event_id', $event->id)
            ->get()
            ->keyBy('user_id');

        return view('officer.activities.attendance', compact('event', 'club', 'members', 'attendance'));
    }

    public function record(Request $request, ClubActivity $event, User $user): RedirectResponse
    {
        $club = $this->officerClub();

        if (! $club || $event->club_id !== $club->id) {
            abort(403);
        }

        $action = $request->input('action'); // 'in' or 'out'

        $record = Attendance::firstOrNew([
            'event_id' => $event->id,
            'user_id'  => $user->id,
        ]);

        // The member is told each time, live (NotifyMemberOfAttendance).
        $told = null;

        if ($action === 'in') {
            $record->time_in    = now();
            $record->time_out   = null;
            $record->recorded_by = auth_user_id();
            $record->save();
            $told = NotifyMemberOfAttendance::CHECKED_IN;
        } elseif ($action === 'out') {
            if ($record->exists && $record->time_in) {
                $record->time_out    = now();
                $record->recorded_by = auth_user_id();
                $record->save();
                $told = NotifyMemberOfAttendance::CHECKED_OUT;
            }
        } elseif ($action === 'undo') {
            if ($record->exists) {
                $record->delete();
                $told = NotifyMemberOfAttendance::REMOVED;
            }
        }

        if ($told) {
            NotifyMemberOfAttendance::dispatch($event->id, $told, $user->id, auth_user_id());
        }

        return back();
    }
}

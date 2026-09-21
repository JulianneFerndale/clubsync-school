<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClubMember;
use App\Models\FeePayment;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(): View
    {
        $userId = auth_user_id();

        // Attendance records with activity + club, grouped by month label
        $attendanceRecords = Attendance::where('user_id', $userId)
            ->with(['activity.club'])
            ->orderByDesc('time_in')
            ->get()
            ->groupBy(fn ($a) => $a->activity?->date?->format('F Y') ?? 'Unknown');

        // Fee payments with fee + club details
        $feePayments = FeePayment::where('user_id', $userId)
            ->with(['fee.club'])
            ->orderByDesc('created_at')
            ->get();

        $pendingFees = $feePayments->where('status', 'pending');
        $paidFees    = $feePayments->where('status', 'paid');

        return view('member.activity', compact(
            'attendanceRecords',
            'pendingFees',
            'paidFees',
        ));
    }
}

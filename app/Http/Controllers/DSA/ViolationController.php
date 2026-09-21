<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Jobs\SendViolationNotificationJob;
use App\Models\AiNotificationQueue;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ViolationController extends Controller
{
    public function index(): View
    {
        $violations = AiNotificationQueue::with('club')
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dsa.violations.index', compact('violations'));
    }

    public function show(AiNotificationQueue $violation): View
    {
        $violation->load('club', 'reviewedBy', 'resolvedBy');

        return view('dsa.violations.show', compact('violation'));
    }

    public function approve(Request $request, AiNotificationQueue $violation): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:5'],
        ]);

        $violation->update([
            'dsa_edited_content' => $validated['content'] !== $violation->draft_content ? $validated['content'] : null,
            'status'             => 'approved',
            'reviewed_by'        => auth_user_id(),
            'reviewed_at'        => now(),
        ]);

        AuditLog::record('violation_statement.approved', $violation, [
            'club_id' => $violation->club_id,
            'edited'  => $violation->dsa_edited_content !== null,
        ]);

        SendViolationNotificationJob::dispatch($violation->id);

        return redirect()->route('dsa.violations.index')->with('success', 'Statement approved and sent to the club.');
    }

    public function dismiss(AiNotificationQueue $violation): RedirectResponse
    {
        $violation->update([
            'status'      => 'discarded',
            'reviewed_by' => auth_user_id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record('violation_statement.dismissed', $violation, ['club_id' => $violation->club_id]);

        return redirect()->route('dsa.violations.index')->with('success', 'Violation dismissed.');
    }
}

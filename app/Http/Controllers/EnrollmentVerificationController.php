<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentVerification;
use App\Services\EnrollmentVerifier;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The people's side of enrollment verification: the cases the automatic check could
 * not decide, for the DSA to approve or reject — and, per the same rules, an
 * administrator (POLICY.md §Registration).
 *
 * Served under both /dsa and /admin, so each sees it inside their own panel; which
 * panel a request came through is also what the decision is recorded as.
 */
class EnrollmentVerificationController extends Controller
{
    /** Cases re-checked per press of "Check them now": about 3 s each, well inside a request. */
    private const RECHECK_BATCH = 5;

    public function __construct(private EnrollmentVerifier $verifier, private GeminiService $gemini) {}

    public function index(Request $request): View
    {
        $pending = EnrollmentVerification::with(['user.course', 'user.department'])
            ->where('status', EnrollmentVerification::STATUS_PENDING)
            ->oldest()
            ->get();

        // People's decisions, and the re-checks that settled a case from this queue once
        // the AI was back — so the DSA sees what happened to a case they did not decide.
        $decided = EnrollmentVerification::with(['user', 'reviewer'])
            ->where(fn ($q) => $q->whereIn('method', ['dsa', 'admin'])
                ->orWhere(fn ($q) => $q->where('method', 'automatic')->whereNotNull('review_reason')))
            ->latest('reviewed_at')
            ->limit(20)
            ->get();

        $awaitingRecheck = $pending->filter(fn (EnrollmentVerification $case) => EnrollmentVerifier::awaitsRecheck($case))->count();

        return view('verifications.index', $this->panel($request) + compact('pending', 'decided', 'awaitingRecheck') + [
            'aiReady' => $this->gemini->isReady(),
        ]);
    }

    /**
     * Run the automatic check now on the cases saved as Pending Verification because it
     * was unavailable — the scheduled `ai:catch-up` does the same every 15 minutes. Each
     * is settled as the check would have at upload (EnrollmentVerifier::recheck()).
     */
    public function recheck(Request $request): RedirectResponse
    {
        $back = redirect()->route($this->route($request, 'index'));

        if (! $this->gemini->isReady()) {
            return $back->with('info', 'The automatic check is still unavailable. These cases are checked again by themselves '
                . 'once it is back, or you can decide them yourself.');
        }

        $tally = $this->verifier->recheckWaiting(self::RECHECK_BATCH);
        $done  = $tally['verified'] + $tally['sent_back'] + $tally['flagged'];

        if ($done === 0) {
            return $back->with('info', $this->gemini->inOutage()
                ? 'The automatic check is still unavailable, so nothing was decided. Try again later, or decide them yourself.'
                : 'Nothing was decided: no case is waiting for the automatic check.');
        }

        $message = "Checked {$done} with the automatic check: {$tally['verified']} verified, "
            . "{$tally['sent_back']} sent back to the student with the reasons, {$tally['flagged']} flagged for you to look at.";

        if ($tally['waiting'] > 0) {
            $message .= " {$tally['waiting']} still waiting" . ($this->gemini->inOutage() ? ' (the check became unavailable again).' : '.');
        }

        return $back->with('success', $message);
    }

    public function show(Request $request, EnrollmentVerification $verification): View
    {
        $verification->load(['user.course', 'user.department', 'reviewer']);

        return view('verifications.show', $this->panel($request) + compact('verification'));
    }

    /** The document itself — decrypted on the way out, never cached anywhere. */
    public function document(EnrollmentVerification $verification): Response
    {
        $bytes = EnrollmentVerifier::documentContents($verification);

        abort_if($bytes === null, 404);

        $headers = [
            'Content-Type'           => $verification->document_mime,
            'Content-Disposition'    => 'inline; filename="proof-of-enrollment-' . $verification->id
                . ($verification->document_mime === 'application/pdf' ? '.pdf' : '.jpg') . '"',
            'Cache-Control'          => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // An image needs nothing else to display. (A PDF is left without it: browsers
        // will not open their PDF viewer inside a sandbox.)
        if ($verification->document_mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return response($bytes, 200, $headers);
    }

    public function approve(Request $request, EnrollmentVerification $verification): RedirectResponse
    {
        if (! $verification->isPending()) {
            return $this->alreadyDecided($request);
        }

        $this->verifier->approve($verification, auth_user_id(), $this->method($request));

        return redirect()->route($this->route($request, 'index'))
            ->with('success', "{$verification->user->name}'s enrollment is verified and their account is now active.");
    }

    public function reject(Request $request, EnrollmentVerification $verification): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'remarks.required' => 'Tell the student why the document was not accepted, so they know what to upload instead.',
            'remarks.min'      => 'Tell the student why the document was not accepted, so they know what to upload instead.',
        ]);

        if (! $verification->isPending()) {
            return $this->alreadyDecided($request);
        }

        $remarks = rtrim(trim($validated['remarks']), '.') . '.';

        $this->verifier->reject($verification, auth_user_id(), $this->method($request), $remarks);

        return redirect()->route($this->route($request, 'index'))
            ->with('success', "{$verification->user->name} has been asked to upload another document.");
    }

    private function alreadyDecided(Request $request): RedirectResponse
    {
        return redirect()->route($this->route($request, 'index'))
            ->with('info', 'Someone has already decided this one.');
    }

    /** 'dsa' or 'admin': which panel the request came through. */
    private function method(Request $request): string
    {
        return $request->routeIs('admin.*') ? 'admin' : 'dsa';
    }

    private function route(Request $request, string $action): string
    {
        return $this->method($request) . '.verifications.' . $action;
    }

    /** @return array{layout: string, prefix: string} */
    private function panel(Request $request): array
    {
        $panel = $this->method($request);

        return [
            'layout' => $panel === 'admin' ? 'layouts.app-admin' : 'layouts.app-dsa',
            'prefix' => $panel . '.verifications.',
        ];
    }
}

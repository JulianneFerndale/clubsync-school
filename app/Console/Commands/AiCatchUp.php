<?php

namespace App\Console\Commands;

use App\Services\EnrollmentVerifier;
use App\Services\GeminiService;
use App\Services\SemesterReportCatchUp;
use Illuminate\Console\Command;

/**
 * Finish the AI work that waited out a Gemini outage (POLICY.md § AI & Automation).
 *
 * While Gemini is down every AI feature takes its manual path, and two of them leave
 * work the AI can still do once it is back:
 *   - proofs of enrollment saved as Pending Verification, re-checked as at upload;
 *   - semester report activities that started from a template, redrafted if untouched.
 *
 * Scheduled every 15 minutes. With nothing waiting it sends nothing; while Gemini is
 * still down the first request fails, the outage is remembered, and the run stops.
 */
class AiCatchUp extends Command
{
    protected $signature = 'ai:catch-up {--limit=25 : The most documents, and the most report activities, sent to the AI in one run}';

    protected $description = 'Re-check proofs of enrollment and redraft template report sections that waited for the AI.';

    public function handle(GeminiService $gemini, EnrollmentVerifier $verifier, SemesterReportCatchUp $reports): int
    {
        if (! $gemini->isAvailable()) {
            $this->info('The AI is not configured; nothing to catch up.');

            return self::SUCCESS;
        }

        if ($gemini->inOutage()) {
            $this->info('Gemini failed in the last ' . GeminiService::OUTAGE_SECONDS . ' seconds; trying again on the next run.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $coe = $verifier->recheckWaiting($limit);
        $this->info("Proofs of enrollment: {$coe['verified']} verified, {$coe['sent_back']} sent back to the student, "
            . "{$coe['flagged']} flagged for the DSA, {$coe['waiting']} still waiting.");

        $drafts = $reports->run($limit);
        $this->info("Semester reports: {$drafts['drafted']} template " . str('activity')->plural($drafts['drafted'])
            . " redrafted by the AI in {$drafts['reports']} " . str('report')->plural($drafts['reports']) . '.');

        if ($gemini->inOutage()) {
            $this->warn('Gemini became unavailable during the run; the rest wait for the next one.');
        }

        return self::SUCCESS;
    }
}

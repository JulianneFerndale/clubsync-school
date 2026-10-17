<?php

use App\Jobs\DetectClubViolationsJob;
use App\Jobs\DispatchSemesterNarrativeReportsJob;
use App\Jobs\NotifyOfficerTurnoverDue;
use App\Jobs\SemesterPresenceReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SemesterPresenceReminderJob)->dailyAt('08:00');
Schedule::job(new DetectClubViolationsJob)->dailyAt('08:00');

// Officers turn over with the school year (POLICY.md §Club Registry). Checked daily
// so the ask lands the day the year turns over; it asks each club only once.
Schedule::job(new NotifyOfficerTurnoverDue)->dailyAt('07:00');

// Draft each club's end-of-semester narrative report the day after the semester ends.
Schedule::job(new DispatchSemesterNarrativeReportsJob)->dailyAt('06:00');

// Finish what waited out a Gemini outage: proofs of enrollment saved as Pending
// Verification, and report activities that started from a template (POLICY.md § AI & Automation).
Schedule::command('ai:catch-up')->everyFifteenMinutes()->withoutOverlapping();

// Prune transient data and alert admins when the database nears its cap.
Schedule::command('retention:check')->dailyAt('02:00');

// Erase private messages past the chat retention period (RA 10173 storage limitation).
Schedule::command('chat:prune')->dailyAt('02:30');

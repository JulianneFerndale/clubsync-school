<?php

namespace App\Console\Commands;

use App\Jobs\DispatchSemesterNarrativeReportsJob;
use App\Models\Semester;
use Illuminate\Console\Command;

/**
 * Generate end-of-semester narrative reports on demand.
 *
 * Normally the daily DispatchSemesterNarrativeReportsJob does this the day after a
 * semester ends. This command covers a missed window and testing. Reports still go to
 * the adviser's review queue — nothing is submitted to the DSA from here.
 */
class GenerateSemesterNarrativeReports extends Command
{
    protected $signature = 'narrative-reports:generate
                            {semester : The semester ID}
                            {--club=* : Only these club IDs (default: every club with completed activities)}
                            {--force : Generate even though the semester has not ended yet}';

    protected $description = 'Draft end-of-semester narrative reports now and send them to the club advisers for review.';

    public function handle(): int
    {
        $semester = Semester::find($this->argument('semester'));

        if (! $semester) {
            $this->error('No semester with that ID.');

            return self::FAILURE;
        }

        if ($semester->end_date->gte(today()) && ! $this->option('force')) {
            $this->error("{$semester->label} ends on {$semester->end_date->format('F j, Y')}. "
                . 'Activities may still be added; pass --force to generate anyway.');

            return self::FAILURE;
        }

        $clubIds = array_map('intval', $this->option('club'));
        $queued  = DispatchSemesterNarrativeReportsJob::dispatchFor($semester, $clubIds, sync: true);

        $this->info(count($queued) === 0
            ? 'Nothing to generate: every club with completed activities already has a report.'
            : 'Generated ' . count($queued) . ' report(s) for club ID(s): ' . implode(', ', $queued) . '. Each is waiting for adviser review.');

        return self::SUCCESS;
    }
}

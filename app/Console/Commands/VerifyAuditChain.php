<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Check the audit log still hashes to what it stored (POLICY.md §Data Privacy:
 * "tamper-resistant audit log").
 *
 * Deliberately read-only. It never writes, never repairs and never records itself,
 * so running it leaves the chain exactly as it found it.
 */
class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';
    protected $description = 'Verify the audit log hash chain has not been altered.';

    public function handle(): int
    {
        $result = AuditLog::verifyChain();

        if ($result['total'] === 0) {
            $this->info('The audit log is empty — nothing to verify.');

            return self::SUCCESS;
        }

        $this->line("Entries checked: {$result['total']}");
        $this->line("Intact:          {$result['intact']}");

        if ($result['legacy_utc'] > 0) {
            // Not a fault: these predate the move to Philippine time and hashed a
            // UTC timestamp. Named explicitly so the cutover is visible in the output
            // rather than quietly absorbed into the intact count.
            $this->line("  of which written before the UTC → Philippine time change: {$result['legacy_utc']}");
        }

        $failed = count($result['broken']) + count($result['unlinked']);

        if ($failed === 0) {
            $this->info('Chain verified — no entry has been altered.');

            return self::SUCCESS;
        }

        if ($result['broken']) {
            $this->error('Contents altered (hash no longer covers the row): #' . implode(', #', $result['broken']));
        }

        if ($result['unlinked']) {
            $this->error('Chain broken (does not follow the entry before it): #' . implode(', #', $result['unlinked']));
        }

        return self::FAILURE;
    }
}

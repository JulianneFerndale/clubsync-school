<?php

namespace App\Console\Commands;

use App\Models\ClubNotification;
use App\Models\User;
use App\Services\DatabaseStorageService;
use Illuminate\Console\Command;

/**
 * Daily storage guard: prunes transient data and, when the database approaches
 * its cap, asks the admin(s) to archive old semesters. Per the chosen policy it
 * never deletes student/financial/activity/audit data on its own.
 */
class RetentionCheck extends Command
{
    protected $signature = 'retention:check';
    protected $description = 'Prune transient data and alert admins when database storage is high.';

    public function handle(DatabaseStorageService $storage): int
    {
        $pruned = $storage->pruneTransient();
        $this->info("Pruned {$pruned['sessions']} expired sessions, {$pruned['notifications']} old read notifications.");

        $status = $storage->status();
        $this->info("Database at {$status['percent']}% of cap ({$status['level']}).");

        if (in_array($status['level'], ['warn', 'critical'], true)) {
            $this->requestAdminCleanup($status);
            $this->warn('Admins notified to review retention / archive old semesters.');
        }

        return self::SUCCESS;
    }

    private function requestAdminCleanup(array $status): void
    {
        $capMb = round($status['cap'] / 1048576);

        foreach (User::where('is_admin', true)->get() as $admin) {
            // One alert per admin per day — don't spam every scheduler run.
            $alreadyAlerted = ClubNotification::where('recipient_id', $admin->id)
                ->where('title', 'like', 'Database storage%')
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if ($alreadyAlerted) {
                continue;
            }

            ClubNotification::create([
                'recipient_id' => $admin->id,
                'sender_type'  => 'system',
                'club_id'      => null,
                'title'        => "Database storage {$status['level']} ({$status['percent']}%)",
                'body'         => "The database is at {$status['percent']}% of its {$capMb} MB cap. "
                    . 'Please review storage and archive old semesters before it fills up.',
                'action_url'   => route('admin.storage.index'),
            ]);
        }
    }
}

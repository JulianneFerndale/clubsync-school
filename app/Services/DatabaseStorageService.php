<?php

namespace App\Services;

use App\Models\RegistrationDraft;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monitors the (Supabase) Postgres database size and prunes transient data.
 *
 * "Full" is detected proactively against a configurable cap (the free-tier 500 MB
 * by default) — never by waiting for 100%, because at the hard limit Supabase puts
 * the project into read-only mode and the app can no longer even raise an alert.
 */
class DatabaseStorageService
{
    public function databaseBytes(): int
    {
        return (int) (DB::selectOne('select pg_database_size(current_database()) as b')->b ?? 0);
    }

    public function capBytes(): int
    {
        return (int) config('retention.database_cap_mb', 500) * 1024 * 1024;
    }

    public function usedPercent(): float
    {
        $cap = $this->capBytes();

        return $cap > 0 ? round($this->databaseBytes() / $cap * 100, 1) : 0.0;
    }

    public function level(): string
    {
        $p = $this->usedPercent();

        return match (true) {
            $p >= config('retention.critical_percent', 90) => 'critical',
            $p >= config('retention.warn_percent', 75)     => 'warn',
            default                                        => 'ok',
        };
    }

    /**
     * Largest tables in the public schema (drives the breakdown on the admin page).
     *
     * @return Collection<int, array{name:string, bytes:int, rows:int}>
     */
    public function tableSizes(int $limit = 12): Collection
    {
        $rows = DB::select(
            'select relname as name, pg_total_relation_size(relid) as bytes, n_live_tup as rows
             from pg_stat_user_tables
             order by pg_total_relation_size(relid) desc
             limit ?',
            [$limit]
        );

        return collect($rows)->map(fn ($r) => [
            'name'  => $r->name,
            'bytes' => (int) $r->bytes,
            'rows'  => (int) $r->rows,
        ]);
    }

    public function status(): array
    {
        return [
            'bytes'   => $this->databaseBytes(),
            'cap'     => $this->capBytes(),
            'percent' => $this->usedPercent(),
            'level'   => $this->level(),
            'tables'  => $this->tableSizes(),
        ];
    }

    /**
     * Delete transient/regenerable rows only. Safe to run unattended — never
     * touches student, financial, activity, or audit data.
     *
     * @return array{sessions:int, notifications:int}
     */
    public function pruneTransient(): array
    {
        $sessions = DB::table('sessions')
            ->where('last_activity', '<', now()->subDays((int) config('retention.session_days', 7))->getTimestamp())
            ->delete();

        // Only READ notifications past the retention window — unread ones are left
        // so offline users still see them (POLICY: notifications persist until seen).
        $notifications = DB::table('notifications')
            ->where('is_read', true)
            ->where('created_at', '<', now()->subDays((int) config('retention.read_notification_days', 90)))
            ->delete();

        // Registrations that never reached their proof of enrollment (RA 10173 storage limitation).
        $drafts = RegistrationDraft::pruneExpired();

        return ['sessions' => $sessions, 'notifications' => $notifications, 'registration_drafts' => $drafts];
    }

    /**
     * Semesters whose bulk data may be archived: everything except the current
     * semester and the single most-recent prior one (those stay hot).
     *
     * @return Collection<int, Semester>
     */
    public function archivableSemesters(): Collection
    {
        $ordered = Semester::orderByDesc('start_date')->get();
        $current = $ordered->firstWhere('is_current', true);

        // Fail closed: if no semester is explicitly flagged as current, we cannot
        // safely tell which semester is still live — so nothing is archivable.
        // This stops an unset is_current flag from exposing the in-progress
        // semester's attendance/fee records to export-and-delete. Archiving stays
        // disabled until an admin marks the current semester.
        if (! $current) {
            return collect();
        }

        // The single most-recent semester that is not the current one stays "hot"
        // (kept alongside the current semester); everything older is archivable.
        $previous = $ordered->first(fn (Semester $s) => $s->id !== $current->id);

        $keep = array_filter([$current->id, $previous?->id]);

        return $ordered->whereNotIn('id', $keep)->values();
    }

    public function isArchivable(Semester $semester): bool
    {
        return $this->archivableSemesters()->contains('id', $semester->id);
    }
}

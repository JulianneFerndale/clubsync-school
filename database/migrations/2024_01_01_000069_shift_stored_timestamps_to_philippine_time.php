<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move every stored timestamp onto the clock the school actually reads.
 *
 * The application ran on UTC until config/app.php was set to Asia/Manila, so every
 * timestamp written before that moment holds a UTC wall clock in a column that has no
 * timezone of its own. Read back under Philippine time those values are eight hours
 * early — an attendance check-in taken at 12:20 PM showed as 4:20 AM. This adds the
 * eight hours once, so the stored wall clock means what the column says it means.
 *
 * Every row present when this runs was written by the UTC application: the config
 * change and this migration ship together, so there is nothing newer to protect and
 * no cutover to filter on. On a fresh database it finds no rows and does nothing.
 *
 * `audit_logs` is deliberately left untouched. Its SHA-256 chain covers `created_at`,
 * so shifting those rows would mean recomputing every hash — rewriting the one table
 * whose whole purpose is to show it has never been rewritten. Those rows keep their
 * original UTC timestamps, and `php artisan audit:verify` reads them accordingly.
 */
return new class extends Migration
{
    private const HOURS = 8;

    /** Never shifted: its hash chain covers the timestamp (see the class docblock). */
    private const EXCLUDED_TABLES = ['audit_logs', 'migrations'];

    public function up(): void
    {
        $this->shift(self::HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::HOURS);
    }

    /**
     * Add $hours to every datetime column in the schema, table by table.
     *
     * The columns are read from the live schema rather than listed here, so a column
     * added between writing this and running it is not silently left on the old clock.
     */
    private function shift(int $hours): void
    {
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (in_array($name, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            foreach ($this->datetimeColumns($name) as $column) {
                DB::table($name)
                    ->whereNotNull($column)
                    ->update([$column => $this->shifted($column, $hours)]);
            }
        }
    }

    /** @return array<int, string> */
    private function datetimeColumns(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $type = strtolower($column['type_name'] ?? $column['type'] ?? '');

            // "timestamp", "timestamptz", "datetime" — but not "date" or "time" on
            // their own, which carry no clock to move.
            if (str_starts_with($type, 'timestamp') || str_starts_with($type, 'datetime')) {
                $columns[] = $column['name'];
            }
        }

        return $columns;
    }

    /** The driver's way of saying "this column, plus N hours". */
    private function shifted(string $column, int $hours)
    {
        $quoted = '"' . str_replace('"', '""', $column) . '"';
        $signed = sprintf('%+d', $hours); // "+8" / "-8" — every driver below reads the sign

        return match (DB::getDriverName()) {
            'pgsql'  => DB::raw("{$quoted} + interval '{$signed} hours'"),
            'sqlite' => DB::raw("datetime({$quoted}, '{$signed} hours')"),
            'mysql', 'mariadb' => DB::raw("DATE_ADD({$quoted}, INTERVAL {$signed} HOUR)"),
            default  => throw new RuntimeException(
                'No timestamp shift defined for the [' . DB::getDriverName() . '] driver.'
            ),
        };
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three changes the user asked for on 2026-10-19:
 *
 * - Clubs have more officer posts: vice president, secretary, auditor and two media mass
 *   officers (internal and external), beside president and treasurer. Each is a users.role
 *   value and a club_officers.position value. The old single `mmo` stays valid for anyone
 *   who already holds it, but is no longer appointed.
 * - An adviser who registers picks the club they advise (one with no adviser yet), and an
 *   administrator confirms it: users.requested_club_id holds the request until then.
 * - Activities go to the club adviser first and the VPA second. A letter the VPA had not
 *   yet read under the old order (`pending_vpa`, which the adviser had not signed) goes to
 *   the adviser instead, so nothing reaches the VPA unsigned by the adviser.
 *
 * Both enum columns are CHECK constraints, replaced as in migration 000084.
 */
return new class extends Migration
{
    private const OLD_ROLES = ['dsa', 'adviser', 'president', 'treasurer', 'mmo', 'member', 'vpa'];

    private const NEW_POSITIONS = ['vice_president', 'secretary', 'auditor', 'mmo_internal', 'mmo_external'];

    private const OLD_POSITIONS = ['president', 'treasurer', 'mmo'];

    public function up(): void
    {
        $this->allow('users', 'role', [...self::OLD_ROLES, ...self::NEW_POSITIONS], 'member');
        $this->allow('club_officers', 'position', [...self::OLD_POSITIONS, ...self::NEW_POSITIONS], null);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('requested_club_id')->nullable()->constrained('clubs')->nullOnDelete();
        });

        DB::table('club_activities')->where('approval_status', 'pending_vpa')->update(['approval_status' => 'pending_approval']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['requested_club_id']);
            $table->dropColumn('requested_club_id');
        });

        // The new posts are given up and their holders become members, so no row is left
        // holding a value the narrower constraints refuse.
        DB::table('club_officers')->whereIn('position', self::NEW_POSITIONS)->delete();
        DB::table('users')->whereIn('role', self::NEW_POSITIONS)->update(['role' => 'member']);

        $this->allow('club_officers', 'position', self::OLD_POSITIONS, null);
        $this->allow('users', 'role', self::OLD_ROLES, 'member');
    }

    /** Let an enum column hold exactly $values. */
    private function allow(string $table, string $column, array $values, ?string $default): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table($table, function (Blueprint $t) use ($column, $values, $default) {
                $definition = $t->enum($column, $values);

                if ($default !== null) {
                    $definition->default($default);
                }

                $definition->change();
            });

            return;
        }

        // Whatever the old check is called, drop every CHECK constraint on this column.
        $checks = DB::select(
            "select con.conname from pg_constraint con
               join pg_class rel on rel.oid = con.conrelid
               join pg_attribute att on att.attrelid = rel.oid and att.attnum = any(con.conkey)
              where rel.relname = ? and att.attname = ? and con.contype = 'c'",
            [$table, $column],
        );

        foreach ($checks as $check) {
            DB::statement(sprintf('alter table "%s" drop constraint "%s"', $table, $check->conname));
        }

        $list = implode(', ', array_map(fn (string $v) => "'{$v}'", $values));

        DB::statement(sprintf('alter table "%s" add constraint "%s_%s_check" check ("%s" in (%s))', $table, $table, $column, $column, $list));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POLICY.md §Event Creation: an activity that needs approval now goes to the Vice
 * President for Administration first, as a room booking letter, and only then to the
 * club adviser.
 *
 * - users.role gains `vpa`: the VPA's account, run by the VPA or by the SSG for them.
 * - club_activities.approval_status gains `pending_vpa`: the letter is with the VPA.
 * - club_activities keeps the letter itself (letter_body), who has signed it and with
 *   which signature (letter_signatures), and the VPA's decision.
 * - users.signature_path: the signature an officer, adviser or VPA signs letters with.
 *   Optional; a letter signed without one says it was signed electronically.
 *
 * Both enum columns are CHECK constraints. PostgreSQL cannot change one through the
 * schema builder, so there the constraint is replaced by hand; SQLite (the tests)
 * rebuilds the table through ->change().
 */
return new class extends Migration
{
    private const ROLES = ['dsa', 'adviser', 'president', 'treasurer', 'mmo', 'member', 'vpa'];

    private const APPROVAL_STATUSES = ['no_approval_needed', 'pending_vpa', 'pending_approval', 'approved', 'rejected'];

    public function up(): void
    {
        $this->allow('users', 'role', self::ROLES, 'member');
        $this->allow('club_activities', 'approval_status', self::APPROVAL_STATUSES, 'no_approval_needed');

        Schema::table('club_activities', function (Blueprint $table) {
            $table->text('letter_body')->nullable();
            $table->boolean('letter_ai_drafted')->default(false);
            $table->json('letter_signatures')->nullable();
            $table->string('vpa_decision', 20)->nullable();
            $table->foreignId('vpa_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vpa_reviewed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });

        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropForeign(['vpa_reviewed_by']);
            $table->dropColumn(['letter_body', 'letter_ai_drafted', 'letter_signatures', 'vpa_decision', 'vpa_reviewed_by', 'vpa_reviewed_at']);
        });

        // Letters still with the VPA go to the adviser, and VPA accounts become members,
        // so no row is left holding a value the narrower constraint refuses.
        DB::table('club_activities')->where('approval_status', 'pending_vpa')->update(['approval_status' => 'pending_approval']);
        DB::table('users')->where('role', 'vpa')->update(['role' => 'member']);

        $this->allow('users', 'role', array_values(array_diff(self::ROLES, ['vpa'])), 'member');
        $this->allow('club_activities', 'approval_status', array_values(array_diff(self::APPROVAL_STATUSES, ['pending_vpa'])), 'no_approval_needed');
    }

    /** Let an enum column hold exactly $values. */
    private function allow(string $table, string $column, array $values, string $default): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table($table, fn (Blueprint $t) => $t->enum($column, $values)->default($default)->change());

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

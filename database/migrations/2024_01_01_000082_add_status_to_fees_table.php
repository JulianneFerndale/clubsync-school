<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fee can be postponed (a later due date) or cancelled (no longer collected), and
 * its members are told either way (App\Jobs\NotifyMembersOfFee).
 *
 * Every existing fee is active. A cancelled fee keeps its payment rows, so what was
 * already paid — and its official receipt — stays on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->string('status', 20)->default('active');      // active | postponed | cancelled
            $table->string('status_reason', 500)->nullable();     // why it was postponed or cancelled
            $table->date('previous_due_date')->nullable();        // the due date before the last postponement
            $table->timestamp('status_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_reason', 'previous_due_date', 'status_changed_at']);
        });
    }
};

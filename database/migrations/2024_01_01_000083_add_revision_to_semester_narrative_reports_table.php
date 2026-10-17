<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The DSA can return a submitted narrative report to the club adviser for revision.
 *
 * A returned report goes back to pending_review, so the status column keeps its two
 * values; these record the latest return: when, by whom and what the DSA asked for.
 * They stay after the adviser resubmits, so the DSA can see the report was revised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semester_narrative_reports', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('submitted_at');
            $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            $table->text('return_remarks')->nullable()->after('returned_by');
        });
    }

    public function down(): void
    {
        Schema::table('semester_narrative_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['returned_at', 'return_remarks']);
        });
    }
};

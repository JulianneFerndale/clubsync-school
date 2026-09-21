<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity review moved from the DSA to the club adviser (POLICY.md §Event Creation),
 * so the column holding the reviewer's rejection reason is no longer the DSA's.
 *
 * Renamed rather than left alone: a column called `dsa_remarks` that only an adviser
 * ever writes to is the kind of drift that later gets read as fact — in a report, in
 * the SRS, or by whoever picks this up next.
 *
 * `club_members.dsa_remarks` is a different column on a different table and keeps its
 * name: member registration remarks really do come from the DSA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->renameColumn('dsa_remarks', 'review_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->renameColumn('review_remarks', 'dsa_remarks');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POLICY.md §Registration: "If no club is mapped to a course, complete registration,
 * flag the student's profile as `Pending Club Assignment`, and alert the DSA Admin."
 *
 * The flag had nowhere to live, so the rule was never enforced — a student whose
 * course maps to no academic club simply ended up in none, with nothing on record
 * saying so and nobody told. This is that flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pending_club_assignment')->default(false)->after('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_club_assignment');
        });
    }
};

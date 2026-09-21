<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            // Array of year levels (1–4) this activity/workshop targets.
            // NULL means the activity is open to every year level.
            $table->json('target_year_levels')->nullable()->after('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn('target_year_levels');
        });
    }
};

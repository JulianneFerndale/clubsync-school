<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            // ID of the mirrored event in the institutional Google Calendar (null = not synced).
            $table->string('google_event_id')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};

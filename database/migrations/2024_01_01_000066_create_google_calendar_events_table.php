<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per event copied into one user's Google Calendar. Replaces the
        // single shared-calendar google_event_id column on club_activities.
        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);   // activity | fee | semester
            $table->unsignedBigInteger('source_id');
            $table->string('slot', 20)->default('main'); // a semester has a start and an end event
            $table->string('google_event_id');
            $table->char('payload_hash', 40);     // skip API calls when nothing changed
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_id', 'slot']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('approved_by');
        });

        Schema::dropIfExists('google_calendar_events');
    }
};

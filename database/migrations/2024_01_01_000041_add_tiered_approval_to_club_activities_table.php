<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->enum('activity_type', [
                'internal_meeting',
                'acle',
                'community_involvement',
                'campus_resource_use',
                'other_external',
            ])->default('internal_meeting')->after('event_type');

            $table->enum('approval_status', [
                'no_approval_needed',
                'pending_approval',
                'approved',
                'rejected',
            ])->default('no_approval_needed')->after('activity_type');

            $table->text('dsa_remarks')->nullable()->after('approval_status');
            $table->string('approval_letter_path')->nullable()->after('dsa_remarks');
            $table->timestamp('approved_at')->nullable()->after('approval_letter_path');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        // Backfill from the legacy event_type column.
        // TODO: drop event_type once the Supabase migration lands (SQLite drop-column support is unreliable pre-3.35).
        DB::table('club_activities')->where('event_type', 'acle')->update(['activity_type' => 'acle']);
        DB::table('club_activities')->where('event_type', 'standard')->update(['activity_type' => 'internal_meeting']);
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['activity_type', 'approval_status', 'dsa_remarks', 'approval_letter_path', 'approved_at']);
        });
    }
};

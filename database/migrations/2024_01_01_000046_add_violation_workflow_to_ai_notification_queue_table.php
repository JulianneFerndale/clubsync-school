<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_notification_queue', function (Blueprint $table) {
            $table->text('description')->nullable()->after('gap_type');
            $table->longText('dsa_edited_content')->nullable()->after('draft_content');
            $table->timestamp('sent_at')->nullable()->after('reviewed_at');
            $table->boolean('is_resolved')->default(false)->after('ai_available');
            $table->text('resolution_note')->nullable()->after('is_resolved');
            $table->timestamp('resolved_at')->nullable()->after('resolution_note');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_notification_queue', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['description', 'dsa_edited_content', 'sent_at', 'is_resolved', 'resolution_note', 'resolved_at']);
        });
    }
};

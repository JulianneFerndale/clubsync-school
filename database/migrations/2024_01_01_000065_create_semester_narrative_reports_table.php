<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semester_narrative_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();

            // The activity records the narrative was written from, frozen at generation
            // so the review checks and the submitted document match what the AI saw.
            $table->json('activity_data');

            $table->longText('draft_content')->nullable();
            $table->longText('adviser_edited_content')->nullable();
            $table->boolean('ai_available')->default(true);
            $table->enum('status', ['pending_review', 'submitted'])->default('pending_review');

            $table->string('pdf_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // One report per club per semester.
            $table->unique(['club_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semester_narrative_reports');
    }
};

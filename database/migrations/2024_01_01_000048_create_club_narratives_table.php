<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_narratives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('club_activity_id')->nullable()->constrained('club_activities')->nullOnDelete();
            $table->string('title');
            $table->longText('draft_content')->nullable();
            $table->longText('adviser_edited_content')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'discarded'])
                  ->default('pending_review');
            $table->boolean('ai_available')->default(true);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_narratives');
    }
};

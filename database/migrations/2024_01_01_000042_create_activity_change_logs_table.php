<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->json('changes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_change_logs');
    }
};

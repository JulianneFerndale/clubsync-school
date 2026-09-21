<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end');
            $table->string('venue');
            $table->text('purpose');
            $table->unsignedInteger('expected_participants');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->enum('event_type', ['standard', 'acle'])->default('standard');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->longText('post_report_content')->nullable();
            $table->enum('post_report_status', ['pending', 'ready', 'submitted'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

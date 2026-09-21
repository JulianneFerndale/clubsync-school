<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->enum('sender_type', ['system', 'user', 'ai_queue'])->default('system');
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->string('action_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

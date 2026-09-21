<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('position', ['president', 'treasurer', 'mmo']);
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['club_id', 'user_id', 'position']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_officers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('acronym');
            $table->string('adviser')->nullable();
            $table->enum('club_type', ['Academic', 'Non-Academic']);
            $table->string('course_slug')->default('all');
            $table->string('department_slug')->default('all');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('name');
            $table->string('profile_photo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};

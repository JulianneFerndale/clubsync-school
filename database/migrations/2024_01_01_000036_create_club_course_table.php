<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_course', function (Blueprint $table) {
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['club_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_course');
    }
};

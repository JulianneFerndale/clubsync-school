<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_semester_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_member_id')->constrained('club_members')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->enum('semester_status', ['active', 'inactive', 'dropped'])->default('active');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_semester_statuses');
    }
};

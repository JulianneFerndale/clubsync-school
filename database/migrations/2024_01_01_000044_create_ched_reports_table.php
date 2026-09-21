<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ched_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_activity_id')->constrained('club_activities');
            $table->foreignId('club_id')->constrained('clubs');
            $table->string('report_type');
            $table->string('pdf_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->text('narrative')->nullable();
            $table->boolean('is_finalized')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ched_reports');
    }
};

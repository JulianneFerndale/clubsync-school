<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The five semester-end reports every club files with the DSA (accomplishment,
 * minutes, financial, property inventory, communications). Officers prepare them
 * from the DSA's templates; the club president alone submits them for review.
 *
 * resource_documents.report_type ties each template to the report it is for, so
 * the submission page can offer the right template beside each report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_documents', function (Blueprint $table) {
            $table->string('report_type', 40)->nullable()->unique()->after('category');
        });

        Schema::create('club_report_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->string('report_type', 40);
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('status', 20)->default('submitted'); // submitted | accepted | returned
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();

            // One live copy of each report per club per semester; a resubmission replaces it.
            $table->unique(['club_id', 'semester_id', 'report_type']);
            $table->index(['semester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_report_submissions');

        Schema::table('resource_documents', function (Blueprint $table) {
            $table->dropUnique(['report_type']);
            $table->dropColumn('report_type');
        });
    }
};

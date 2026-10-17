<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof that a registering student is a bona fide SCC student: a Certificate of
 * Enrollment or the front of their school ID, showing the same full name, EDP number
 * and course they registered with (POLICY.md §Registration).
 *
 * users.enrollment_status is what the gate reads on every request, so it lives on the
 * user: null for accounts that never had to verify (staff, and students who registered
 * before this existed), otherwise verified / pending / rejected.
 *
 * enrollment_verifications is one row per decision. The uploaded file is kept only
 * while a person still has to look at it (a pending review), encrypted, and deleted
 * the moment it is decided; what stays is the outcome — which document, decided how,
 * by whom and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('enrollment_status', 20)->nullable()->after('pending_club_assignment');
        });

        Schema::create('enrollment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20);                          // verified | pending | rejected
            $table->string('method', 20)->nullable();              // automatic | dsa | admin — who decided
            $table->string('review_reason', 30)->nullable();       // why a person had to look at it
            $table->string('document_type', 40)->nullable();       // certificate_of_enrollment | school_id | ...
            $table->string('document_path')->nullable();           // encrypted file; only while pending
            $table->string('document_mime', 60)->nullable();
            $table->text('findings')->nullable();                  // encrypted: what the automatic check read
            $table->text('remarks')->nullable();                   // the reviewer's reason for a rejection
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_verifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('enrollment_status');
        });
    }
};

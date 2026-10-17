<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The administrator's document library, which replaces the per-activity CHED reports.
 *
 * ched_memorandums: the CHED issuances SCC's student affairs work is governed by
 * (memorandum orders, memorandums, advisories), each with its signed PDF.
 *
 * resource_documents: files every user may download — the SCC club handbook and the
 * report templates clubs fill in (activity proposals, accomplishment reports, …).
 *
 * The old ched_reports table is left in place so no submitted report is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ched_memorandums', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                 // order | memorandum | advisory
            $table->string('memo_number', 20);          // "09", "2-A"
            $table->unsignedSmallInteger('series_year');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->date('date_issued');
            $table->date('effective_date')->nullable();
            $table->string('status', 20)->default('in_effect'); // in_effect | superseded | revoked
            $table->string('source_url')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // CHED numbers restart every series, and each kind of issuance has its own run.
            $table->unique(['type', 'memo_number', 'series_year']);
            $table->index(['status', 'series_year']);
        });

        Schema::create('resource_documents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);             // handbook | template
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_documents');
        Schema::dropIfExists('ched_memorandums');
    }
};

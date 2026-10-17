<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The semester narrative report became the five DSA documents (accomplishment,
 * minutes, financial, property inventory, communications), written per activity.
 *
 * ai_sections keeps what Gemini drafted for each activity, unchanged; sections is the
 * adviser's working copy, which the documents print. Reports drafted before this
 * change have neither and keep their single narrative and PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semester_narrative_reports', function (Blueprint $table) {
            $table->json('ai_sections')->nullable()->after('adviser_edited_content');
            $table->json('sections')->nullable()->after('ai_sections');
        });
    }

    public function down(): void
    {
        Schema::table('semester_narrative_reports', function (Blueprint $table) {
            $table->dropColumn(['ai_sections', 'sections']);
        });
    }
};

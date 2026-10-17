<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos officers attach to an activity for the semester-end reports: documentation
 * shots, the written minutes, the program, communication letters, publication
 * materials, receipts and the properties used. Each file lives in the reporting
 * folder (report-photos/{club}/{activity}/ on the local disk); this table is the
 * index the report documents are compiled from.
 *
 * Receipts carry their amount and receipt number, and properties their quantity and
 * condition, because those are the figures the financial and inventory reports list.
 * The activity's budget is what the financial report sets the receipts against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('club_activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('caption')->nullable();

            $table->decimal('amount', 12, 2)->nullable();          // receipts
            $table->string('reference_number', 60)->nullable();    // receipts: OR / receipt no.
            $table->unsignedInteger('quantity')->nullable();       // properties
            $table->string('remarks')->nullable();                 // properties: condition

            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_activity_id', 'category']);
        });

        Schema::table('club_activities', function (Blueprint $table) {
            $table->decimal('budget_amount', 12, 2)->nullable()->after('expected_participants');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropColumn('budget_amount');
        });

        Schema::dropIfExists('activity_photos');
    }
};

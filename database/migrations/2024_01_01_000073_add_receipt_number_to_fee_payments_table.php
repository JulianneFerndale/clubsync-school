<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment needs something to be receipted by.
 *
 * Issued once and kept: if a payment is undone and confirmed again it keeps the same
 * number, so one payment never ends up with two receipts in circulation, and the
 * series has no gaps to explain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->string('receipt_number', 32)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_scan_log', function (Blueprint $table) {
            $table->id();
            $table->timestamp('scanned_at');
            $table->integer('gaps_found')->default(0);
            $table->boolean('all_clear')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_scan_log');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Add new normalized type alongside the existing club_type column
            $table->enum('type', ['academic', 'non_academic'])->nullable()->after('club_type');
            $table->foreignId('college_id')->nullable()->after('type')
                  ->constrained('colleges')->nullOnDelete();
            $table->foreignId('adviser_id')->nullable()->after('college_id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropForeign(['adviser_id']);
            $table->dropColumn(['type', 'college_id', 'adviser_id']);
        });
    }
};

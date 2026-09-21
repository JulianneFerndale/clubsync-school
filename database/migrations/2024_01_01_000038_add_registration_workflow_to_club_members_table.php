<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->enum('registration_status', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            $table->text('dsa_remarks')->nullable()->after('registration_status');
            $table->foreignId('submitted_by')->nullable()->after('dsa_remarks')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['registration_status', 'dsa_remarks', 'approved_at']);
        });
    }
};

composer require laravel/breeze --dev<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->timestamp('joined_at')->nullable()->after('date_joined');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending')->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropColumn(['joined_at', 'status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Advisers (faculty) have no EDP number — they use employee_number instead.
        Schema::table('users', function (Blueprint $table) {
            $table->string('edp_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('edp_number')->nullable(false)->change();
        });
    }
};

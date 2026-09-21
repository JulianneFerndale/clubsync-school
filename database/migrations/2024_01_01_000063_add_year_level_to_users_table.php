<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1–4 for students. Nullable so non-student roles (adviser, dsa, admin)
            // and pre-existing accounts remain valid; the registration form requires it.
            $table->unsignedTinyInteger('year_level')->nullable()->after('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('year_level');
        });
    }
};

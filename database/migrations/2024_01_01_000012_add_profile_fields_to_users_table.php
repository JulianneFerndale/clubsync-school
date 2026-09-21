<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('edp_number', 20)->after('last_name');
            $table->string('mobile_number', 20)->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('mobile_number')->constrained('departments')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->after('department_id')->constrained('courses')->nullOnDelete();
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->dropForeign(['department_id']);
            $table->dropForeign(['course_id']);
            $table->dropColumn(['first_name', 'last_name', 'edp_number', 'mobile_number', 'department_id', 'course_id']);
        });
    }
};

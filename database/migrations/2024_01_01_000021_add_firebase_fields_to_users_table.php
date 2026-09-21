<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->unique()->nullable()->after('id');
            $table->enum('role', ['dsa', 'adviser', 'president', 'treasurer', 'mmo', 'member'])
                  ->default('member')->after('firebase_uid');
            $table->foreignId('college_id')->nullable()->after('course_id')
                  ->constrained('colleges')->nullOnDelete();
            $table->string('profile_photo_url')->nullable()->after('college_id');
            $table->timestamp('last_password_changed')->nullable()->after('profile_photo_url');
            $table->boolean('is_suspended')->default(false)->after('last_password_changed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropColumn([
                'firebase_uid',
                'role',
                'college_id',
                'profile_photo_url',
                'last_password_changed',
                'is_suspended',
            ]);
        });
    }
};

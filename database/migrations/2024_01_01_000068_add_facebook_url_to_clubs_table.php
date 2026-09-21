<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The club's official Facebook page. Nullable: not every club has one.
        // Initial links are loaded by ClubFacebookLinkSeeder; the DSA edits them after.
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('facebook_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('facebook_url');
        });
    }
};

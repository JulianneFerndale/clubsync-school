<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * POLICY.md §Authentication: a student who registers with their SCC Google account
 * sets no password and signs in with Google only. Such an account has no password to
 * store, so the column may be empty — and an empty one is how ClubSync knows the
 * account signs in with Google (User::signsInWithGoogleOnly).
 *
 * Firebase checks every password; this column is never read to sign anyone in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A Google-only account gets an unusable random password back, so the column
        // can be required again. It still signs in with Google.
        DB::table('users')->whereNull('password')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make(bin2hex(random_bytes(32)))]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};

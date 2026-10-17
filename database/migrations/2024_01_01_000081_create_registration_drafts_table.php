<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POLICY.md §Registration: a registration that stops before the proof of enrollment is
 * kept for a short while, so signing in again resumes it at the step it reached instead
 * of saying the address is not registered.
 *
 * No account exists yet, so this is not a `users` row. The details are encrypted
 * (POLICY.md §Data Privacy), the password and Firebase tokens are never stored, and the
 * row is deleted when the account is created or once `expires_at` passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_drafts', function (Blueprint $table) {
            $table->id();
            // The Firebase account claimed at step 1 — what a password sign-in finds it by.
            $table->string('firebase_uid')->unique();
            // HMAC of the address — what a Google sign-in finds it by, without storing it in the clear.
            $table->string('email_hash', 64)->unique();
            $table->text('details');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_drafts');
    }
};

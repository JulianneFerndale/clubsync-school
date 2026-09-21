<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per pair of users. The pair is stored low-id first so the unique
        // index makes "A↔B" and "B↔A" the same conversation.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id']);
            $table->index('user_high_id');
        });

        // Each participant's private view of the conversation. Message ids are used
        // as the read/cleared markers because they are strictly increasing, unlike
        // second-resolution timestamps.
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->unsignedBigInteger('cleared_through_message_id')->nullable(); // "delete conversation for me"
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        // Message bodies are encrypted at rest by the model (Laravel `encrypted`
        // cast, AES-256 with APP_KEY), so the column holds ciphertext — hence text.
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();          // null once the sender unsends it
            $table->timestamp('retracted_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index('created_at');
        });

        Schema::create('chat_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });

        // RA 10173 transparency: a user must read the chat privacy notice before
        // their first message.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('chat_privacy_acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('chat_privacy_acknowledged_at');
        });

        Schema::dropIfExists('chat_blocks');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};

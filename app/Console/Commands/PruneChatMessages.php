<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Console\Command;

/**
 * RA 10173 storage limitation for private messages: permanently delete messages
 * older than config('chat.retention_days'), then remove conversations left empty.
 */
class PruneChatMessages extends Command
{
    protected $signature = 'chat:prune';
    protected $description = 'Permanently delete private messages older than the chat retention period.';

    public function handle(): int
    {
        $days = (int) config('chat.retention_days');
        $cutoff = now()->subDays($days);

        $messages = 0;
        do {
            $ids = ChatMessage::where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            $messages += $ids->isEmpty() ? 0 : ChatMessage::whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        // Emptied by the prune, or opened a day ago and never used.
        $conversations = Conversation::whereDoesntHave('messages')
            ->where('updated_at', '<', now()->subDay())
            ->delete();

        if ($messages > 0 || $conversations > 0) {
            AuditLog::record('chat.messages.pruned', null, [
                'retention_days'        => $days,
                'messages_deleted'      => $messages,
                'conversations_deleted' => $conversations,
            ]);
        }

        $this->info("Deleted {$messages} messages older than {$days} days and {$conversations} empty conversations.");

        return self::SUCCESS;
    }
}

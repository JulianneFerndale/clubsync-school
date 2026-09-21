{{--
    One message bubble. The chat page's script builds the same markup for messages
    that arrive live (renderMessage in chat/show) — keep the two in step.
--}}
@php($mine = $message->sender_id === $me->id)
<div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}" data-mine="{{ $mine ? 1 : 0 }}">
    <div class="max-w-[80%] min-w-0">
        @if($message->isRetracted())
            <div class="rounded-2xl px-3.5 py-2 text-sm italic text-gray-400 border border-dashed border-gray-300">Message deleted</div>
        @else
            <div class="rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words {{ $mine ? 'bg-[#07873e] text-white rounded-br-md' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-md' }}">{{ $message->body }}</div>
        @endif
        <div class="flex items-center gap-2 mt-1 px-1 text-[11px] text-gray-400 {{ $mine ? 'justify-end' : '' }}">
            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('M j, g:i A') }}</time>
            @if($mine && ! $message->isRetracted())
                <form method="POST" action="{{ route('chat.messages.retract', [$message->conversation_id, $message->id]) }}" data-chat-retract>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="hover:text-red-600 hover:underline">Unsend</button>
                </form>
            @endif
        </div>
    </div>
</div>

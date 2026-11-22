<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Models\Message;

final class ConversationContextBuilder
{
    private const HISTORY_LIMIT = 10;
    private const MESSAGE_CONTENT_LIMIT = 2000;

    /**
     * Build bounded context for AI interpretation.
     *
     * @return array<string, mixed>
     */
    public function build(
        Conversation $conversation,
        string $language,
        string $pendingQuestion,
        ?int $excludeMessageId = null,
    ): array {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->with('outboundMessage')
            ->latest('id');

        if ($excludeMessageId !== null) {
            $query->where('id', '!=', $excludeMessageId);
        }

        $messages = $query
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $history = [];

        foreach ($messages as $message) {
            $history[] = [
                'role' => 'user',
                'content' => mb_substr(
                    $message->content,
                    0,
                    self::MESSAGE_CONTENT_LIMIT
                ),
                'timestamp' => $message->created_at?->toIso8601String(),
            ];

            $outbound = $message->outboundMessage;

            if ($outbound !== null && $outbound->status === 'sent') {
                $history[] = [
                    'role' => 'assistant',
                    'content' => mb_substr(
                        $outbound->content,
                        0,
                        self::MESSAGE_CONTENT_LIMIT
                    ),
                    'timestamp' => $outbound->created_at?->toIso8601String(),
                ];
            }
        }

        return [
            'conversation_id' => $conversation->id,
            'current_state' => $conversation->state->value,
            'previous_state' => $conversation->previous_state,
            'language' => $language,
            'pending_question' => $pendingQuestion,
            'conversation_history' => $history,
        ];
    }
}

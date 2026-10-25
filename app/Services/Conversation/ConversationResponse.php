<?php

namespace App\Services\Conversation;

final readonly class ConversationResponse
{
    public function __construct(
        public string $reply,
        public ConversationState $nextState,
        public bool $requiresClarification = false,
    ) {
    }
}

<?php

namespace App\Services\Conversation;

final readonly class PreparedConversation
{
    public function __construct(
        public ConversationResponse $response,
        public int $userId,
        public ?int $conversationId,
        public ?string $expectedState,
        public ?string $expectedPreviousState,
        public ?string $expectedStatus,
        public ?int $expectedLatestInboundMessageId,
        public bool $createConversation = false,
        public ?string $preferredLanguage = null,
        public bool $updatePreferredLanguage = false,
        public ?string $previousState = null,
        public bool $updatePreviousState = false,
        public ?string $conversationStatus = null,
    ) {}
}

<?php

namespace Tests\Unit\Services\Conversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Conversation\ConversationContextBuilder;
use App\Services\Conversation\ConversationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ConversationContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ConversationContextBuilder();
    }

    public function test_it_builds_history_in_chronological_order(): void
    {
        [$user, $conversation] = $this->createConversation();

        $first = $this->createInbound($user, $conversation, 'First message');
        $second = $this->createInbound($user, $conversation, 'Second message');

        $this->createSentOutbound($user, $first, 'First reply');
        $this->createSentOutbound($user, $second, 'Second reply');

        $context = $this->build($conversation);

        $this->assertSame([
            'First message',
            'First reply',
            'Second message',
            'Second reply',
        ], array_column($context['conversation_history'], 'content'));
    }

    public function test_it_does_not_include_messages_from_another_conversation(): void
    {
        [$user, $conversation] = $this->createConversation();
        [, $otherConversation] = $this->createConversation($user);

        $this->createInbound($user, $otherConversation, 'Other conversation');

        $this->createInbound($user, $conversation, 'Current conversation');

        $context = $this->build($conversation);

        $this->assertSame(
            ['Current conversation'],
            array_column($context['conversation_history'], 'content')
        );
    }

    public function test_it_excludes_the_current_inbound_message(): void
    {
        [$user, $conversation] = $this->createConversation();

        $previous = $this->createInbound(
            $user,
            $conversation,
            'Previous message'
        );

        $current = $this->createInbound(
            $user,
            $conversation,
            'Current message'
        );

        $context = $this->build($conversation, $current->id);

        $this->assertSame(
            ['Previous message'],
            array_column($context['conversation_history'], 'content')
        );
    }

    public function test_it_includes_only_confirmed_sent_outbound_replies(): void
    {
        [$user, $conversation] = $this->createConversation();

        $sentMessage = $this->createInbound(
            $user,
            $conversation,
            'Message with sent reply'
        );

        $this->createSentOutbound($user, $sentMessage, 'Confirmed reply');

        $pendingMessage = $this->createInbound(
            $user,
            $conversation,
            'Message with pending reply'
        );

        $this->createOutbound($user, $pendingMessage, 'Pending reply', 'pending');

        $failedMessage = $this->createInbound(
            $user,
            $conversation,
            'Message with failed reply'
        );

        $this->createOutbound($user, $failedMessage, 'Failed reply', 'failed');

        $context = $this->build($conversation);

        $this->assertSame([
            'Message with sent reply',
            'Confirmed reply',
            'Message with pending reply',
            'Message with failed reply',
        ], array_column($context['conversation_history'], 'content'));
    }

    public function test_it_limits_history_to_ten_inbound_messages(): void
    {
        [$user, $conversation] = $this->createConversation();

        for ($i = 1; $i <= 12; $i++) {
            $this->createInbound(
                $user,
                $conversation,
                "Message {$i}"
            );
        }

        $context = $this->build($conversation);

        $this->assertCount(10, array_filter(
            $context['conversation_history'],
            fn (array $entry) => $entry['role'] === 'user'
        ));

        $this->assertSame(
            'Message 3',
            $context['conversation_history'][0]['content']
        );

        $this->assertSame(
            'Message 12',
            $context['conversation_history'][9]['content']
        );
    }

    public function test_it_truncates_message_content_to_two_thousand_characters(): void
    {
        [$user, $conversation] = $this->createConversation();

        $this->createInbound(
            $user,
            $conversation,
            str_repeat('A', 2500)
        );

        $context = $this->build($conversation);

        $this->assertSame(
            2000,
            mb_strlen($context['conversation_history'][0]['content'])
        );
    }

    public function test_it_includes_conversation_state_language_and_pending_question(): void
    {
        [, $conversation] = $this->createConversation();

        $context = $this->build(
            $conversation,
            null,
            'What is your full name?'
        );

        $this->assertSame($conversation->id, $context['conversation_id']);
        $this->assertSame(
            $conversation->state->value,
            $context['current_state']
        );
        $this->assertSame('en', $context['language']);
        $this->assertSame(
            'What is your full name?',
            $context['pending_question']
        );
    }

    private function createConversation(?User $user = null): array
    {
        $user ??= User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::CV_SELECTION,
            'status' => 'active',
        ]);

        return [$user, $conversation];
    }

    private function createInbound(
        User $user,
        Conversation $conversation,
        string $content,
    ): Message {
        return Message::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'provider' => 'whatsapp',
            'provider_message_id' => 'in-' . uniqid(),
            'direction' => 'inbound',
            'content' => $content,
            'status' => 'processed',
            'received_at' => now(),
        ]);
    }

    private function createSentOutbound(
        User $user,
        Message $message,
        string $content,
    ): OutboundMessage {
        return $this->createOutbound(
            $user,
            $message,
            $content,
            'sent'
        );
    }

    private function createOutbound(
        User $user,
        Message $message,
        string $content,
        string $status,
    ): OutboundMessage {
        return OutboundMessage::query()->create([
            'inbound_message_id' => $message->id,
            'user_id' => $user->id,
            'provider' => 'whatsapp',
            'idempotency_key' => 'key-' . uniqid(),
            'recipient_phone_e164' => '+255700000001',
            'content' => $content,
            'status' => $status,
        ]);
    }

    private function build(
        Conversation $conversation,
        ?int $excludeMessageId = null,
        string $pendingQuestion = 'Choose an option.',
    ): array {
        return $this->builder->build(
            $conversation,
            'en',
            $pendingQuestion,
            $excludeMessageId,
        );
    }
}

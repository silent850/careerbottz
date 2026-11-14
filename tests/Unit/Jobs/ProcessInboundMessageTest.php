<?php

namespace Tests\Unit\Jobs;
use App\Services\Conversation\ResponseGeneratorContract;
use App\Jobs\ProcessInboundMessage;
use App\Models\Message;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AI\IntentClassifierContract;
use App\Services\Conversation\ConversationEngine;
use App\Services\Conversation\ConversationLanguage;
use App\Services\Conversation\ConversationPromptBuilder;
use App\Services\WhatsApp\WhatsAppGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessInboundMessageTest extends TestCase
{
    use RefreshDatabase;

   private function createInboundMessage(string $suffix = '001'): Message
{
    $providerMessageId = 'wamid.job-test-' . $suffix;

    $phoneNumber = $suffix === '001'
        ? '+255712345678'
        : '+2557' . str_pad(
            (string) (abs(crc32($suffix)) % 100000000),
            8,
            '0',
            STR_PAD_LEFT,
        );

    $user = User::factory()->create([
        'phone_e164' => $phoneNumber,
        'preferred_language' => 'en',
    ]);

    WebhookEvent::query()->create([
        'provider' => 'whatsapp',
        'provider_event_id' => $providerMessageId,
        'event_type' => 'message.inbound',
        'status' => 'received',
        'payload' => [
            'provider_message_id' => $providerMessageId,
            'sender_phone_e164' => $phoneNumber,
            'text' => 'Hello',
        ],
        'received_at' => now(),
    ]);

    return Message::query()->create([
        'user_id' => $user->id,
        'provider' => 'whatsapp',
        'provider_message_id' => $providerMessageId,
        'direction' => 'inbound',
        'content' => 'Hello',
        'status' => 'received',
        'received_at' => now(),
    ]);
}

    private function conversationEngine(): ConversationEngine
{
    return new ConversationEngine(
        Mockery::mock(IntentClassifierContract::class),
        new ConversationLanguage(),
        new ConversationPromptBuilder(),
        Mockery::mock(ResponseGeneratorContract::class),
    );
}

    private function createOutboundMessage(
        Message $message,
        string $status,
    ): OutboundMessage {
        return OutboundMessage::query()->create([
            'inbound_message_id' => $message->id,
            'user_id' => $message->user_id,
            'provider' => $message->provider,
            'idempotency_key' => hash(
                'sha256',
                $message->provider . ':' . $message->provider_message_id,
            ),
            'recipient_phone_e164' => '+255712345678',
            'content' => 'Existing outbound reply',
            'status' => $status,
            'attempt_count' => $status === 'pending' ? 0 : 1,
        ]);
    }

    public function test_it_processes_an_inbound_message_and_records_outbound_success(): void
    {
        $message = $this->createInboundMessage();

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);

        $gateway->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                '+255712345678',
                Mockery::type('string'),
            )
            ->andReturn([
                'success' => true,
                'provider_message_id' => 'wamid.outbound-001',
            ]);

        $job = new ProcessInboundMessage((int) $message->id);

        $job->handle($this->conversationEngine(), $gateway);

        $outbound = OutboundMessage::query()
            ->where('inbound_message_id', $message->id)
            ->sole();

        $this->assertSame('sent', $outbound->status);
        $this->assertSame('whatsapp', $outbound->provider);
        $this->assertSame('+255712345678', $outbound->recipient_phone_e164);
        $this->assertNotEmpty($outbound->content);
        $this->assertSame('wamid.outbound-001', $outbound->provider_message_id);
        $this->assertSame(1, $outbound->attempt_count);
        $this->assertNotNull($outbound->sent_at);
        $this->assertNull($outbound->unknown_at);
        $this->assertSame(
            hash('sha256', 'whatsapp:wamid.job-test-001'),
            $outbound->idempotency_key,
        );

        $this->assertSame('processed', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->processed_at);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'whatsapp',
            'provider_event_id' => 'wamid.job-test-001',
            'status' => 'processed',
        ]);

        // Re-running a completed inbound job must not send another reply.
        $job->handle($this->conversationEngine(), $gateway);

        $this->assertSame(
            1,
            OutboundMessage::query()
                ->where('inbound_message_id', $message->id)
                ->count(),
        );
    }

    public function test_gateway_failure_records_unknown_delivery_without_marking_message_processed(): void
    {
        $message = $this->createInboundMessage();

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);

        $gateway->shouldReceive('sendTextMessage')
            ->once()
            ->with('+255712345678', Mockery::type('string'))
            ->andThrow(new RuntimeException('Gateway unavailable.'));

        try {
            $job = new ProcessInboundMessage((int) $message->id);
            $job->handle($this->conversationEngine(), $gateway);

            $this->fail('Expected the gateway exception to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gateway unavailable.', $exception->getMessage());
        }

        $outbound = OutboundMessage::query()
            ->where('inbound_message_id', $message->id)
            ->sole();

        $this->assertSame('unknown', $outbound->status);
        $this->assertSame(1, $outbound->attempt_count);
        $this->assertNotNull($outbound->unknown_at);
        $this->assertSame('Gateway unavailable.', $outbound->last_error);
        $this->assertNull($outbound->sent_at);
        $this->assertNull($outbound->provider_message_id);

        $this->assertNull($message->fresh()->processed_at);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('webhook_events', [
            'provider_event_id' => 'wamid.job-test-001',
            'status' => 'received',
        ]);
    }

    public function test_retry_does_not_blindly_resend_an_outbound_message_with_unknown_delivery(): void
    {
        $message = $this->createInboundMessage();

        $firstGateway = Mockery::mock(WhatsAppGatewayClient::class);

        $firstGateway->shouldReceive('sendTextMessage')
            ->once()
            ->andThrow(new RuntimeException('Gateway timed out.'));

        try {
            (new ProcessInboundMessage((int) $message->id))
                ->handle($this->conversationEngine(), $firstGateway);

            $this->fail('Expected the gateway exception to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gateway timed out.', $exception->getMessage());
        }

        $retryGateway = Mockery::mock(WhatsAppGatewayClient::class);
        $retryGateway->shouldNotReceive('sendTextMessage');

        // The existing unknown outbound record must prevent another send.
        (new ProcessInboundMessage((int) $message->id))
            ->handle($this->conversationEngine(), $retryGateway);

        $outbound = OutboundMessage::query()
            ->where('inbound_message_id', $message->id)
            ->sole();

        $this->assertSame('unknown', $outbound->status);
        $this->assertSame(1, $outbound->attempt_count);
        $this->assertNull($message->fresh()->processed_at);
        $this->assertSame('received', $message->fresh()->status);
    }

    public function test_it_does_not_send_again_when_message_is_already_processed(): void
    {
        $message = $this->createInboundMessage();

        $message->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);
        $gateway->shouldNotReceive('sendTextMessage');

        $job = new ProcessInboundMessage((int) $message->id);
        $job->handle($this->conversationEngine(), $gateway);

        $this->assertNotNull($message->fresh()->processed_at);

        $this->assertSame(
            0,
            OutboundMessage::query()
                ->where('inbound_message_id', $message->id)
                ->count(),
        );
    }

    public function test_conversation_changes_roll_back_when_outbound_intent_cannot_be_saved(): void
    {
        $message = $this->createInboundMessage();

        // Reserve this inbound message's idempotency key on another record.
        $otherUser = User::factory()->create([
            'phone_e164' => '+255712345679',
            'preferred_language' => 'en',
        ]);

        $otherMessage = Message::query()->create([
            'user_id' => $otherUser->id,
            'provider' => 'whatsapp',
            'provider_message_id' => 'wamid.other-message',
            'direction' => 'inbound',
            'content' => 'Other message',
            'status' => 'received',
            'received_at' => now(),
        ]);

        OutboundMessage::query()->create([
            'inbound_message_id' => $otherMessage->id,
            'user_id' => $otherUser->id,
            'provider' => 'whatsapp',
            'idempotency_key' => hash(
                'sha256',
                'whatsapp:wamid.job-test-001',
            ),
            'recipient_phone_e164' => '+255712345679',
            'content' => 'Existing reply',
            'status' => 'sent',
            'provider_message_id' => 'wamid.existing-reply',
            'attempt_count' => 1,
            'sent_at' => now(),
        ]);

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);
        $gateway->shouldNotReceive('sendTextMessage');

        try {
            (new ProcessInboundMessage((int) $message->id))
                ->handle($this->conversationEngine(), $gateway);

            $this->fail('Expected the outbound idempotency constraint to fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            // Expected: the outbound intent cannot be inserted.
            $this->assertNotSame('', $exception->getMessage());
        }

        // The conversation engine's writes should have rolled back.
        $this->assertDatabaseMissing('conversations', [
            'user_id' => $message->user_id,
        ]);

        // The inbound message is eligible for a later retry.
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'status' => 'received',
            'processed_at' => null,
        ]);

        // The pre-existing outbound record remains intact.
        $this->assertDatabaseHas('outbound_messages', [
            'inbound_message_id' => $otherMessage->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.existing-reply',
        ]);
    }

    public function test_existing_non_pending_outbound_statuses_are_not_resent_and_release_inbound_claim(): void
    {
        $statuses = [
            'unknown',
            'sending',
            'sent',
            'failed',
        ];

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);
        $gateway->shouldNotReceive('sendTextMessage');

        foreach ($statuses as $status) {
            $message = $this->createInboundMessage($status);

            $outbound = $this->createOutboundMessage(
                $message,
                $status,
            );

            (new ProcessInboundMessage((int) $message->id))
                ->handle($this->conversationEngine(), $gateway);

            $this->assertSame(
                $status,
                $outbound->fresh()->status,
                "Outbound status {$status} should remain unchanged.",
            );

            $this->assertSame(
                1,
                $outbound->fresh()->attempt_count,
            );

            $this->assertSame(
                'received',
                $message->fresh()->status,
                "Inbound message with outbound status {$status} should not remain processing.",
            );

            $this->assertNull($message->fresh()->processed_at);
        }
    }

    public function test_existing_pending_outbound_message_can_be_delivered_without_regenerating_reply(): void
    {
        $message = $this->createInboundMessage();

        $outbound = $this->createOutboundMessage(
            $message,
            'pending',
        );

        $gateway = Mockery::mock(WhatsAppGatewayClient::class);

        $gateway->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                '+255712345678',
                'Existing outbound reply',
            )
            ->andReturn([
                'success' => true,
                'provider_message_id' => 'wamid.pending-retry-001',
            ]);

        (new ProcessInboundMessage((int) $message->id))
            ->handle($this->conversationEngine(), $gateway);

        $this->assertSame('sent', $outbound->fresh()->status);
        $this->assertSame(1, $outbound->fresh()->attempt_count);
        $this->assertSame(
            'wamid.pending-retry-001',
            $outbound->fresh()->provider_message_id,
        );

        $this->assertSame('processed', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->processed_at);
    }


public function test_duplicate_job_does_not_process_message_with_active_claim(): void
{
    $message = $this->createInboundMessage();

    // Simulate another worker already processing this inbound message.
    $message->update([
        'status' => 'processing',
        'updated_at' => now(),
    ]);

    $conversationEngine = $this->conversationEngine();

    $gateway = Mockery::mock(WhatsAppGatewayClient::class);
    $gateway->shouldNotReceive('sendTextMessage');

    // A duplicate job must not take over an active processing claim.
    (new ProcessInboundMessage((int) $message->id))
        ->handle($conversationEngine, $gateway);

    $this->assertSame('processing', $message->fresh()->status);

    $this->assertNull($message->fresh()->processed_at);

    $this->assertDatabaseMissing('outbound_messages', [
        'inbound_message_id' => $message->id,
    ]);
}

}
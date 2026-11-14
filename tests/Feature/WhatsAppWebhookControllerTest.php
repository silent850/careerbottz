<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundMessage;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp_gateway.secret' => 'test-only-secret-0123456789abcdef',
        ]);
    }

    public function test_new_inbound_message_is_persisted_and_job_is_dispatched(): void
    {
        Queue::fake();

        $response = $this->withHeader(
            'X-Gateway-Secret',
            'test-only-secret-0123456789abcdef',
        )->postJson('/api/internal/whatsapp/inbound', [
            'provider_message_id' => 'wamid.webhook-test-001',
            'sender_phone_e164' => '+255712345678',
            'text' => 'Hello CareerBotTz',
        ]);

        $response->assertCreated();

        $response->assertJsonPath('data.duplicate', false);

        $messageId = Message::query()
            ->where('provider_message_id', 'wamid.webhook-test-001')
            ->value('id');

        $this->assertNotNull($messageId);

        Queue::assertPushed(
            ProcessInboundMessage::class,
            fn (ProcessInboundMessage $job): bool =>
                $job->messageId === (int) $messageId,
        );

        $this->assertDatabaseHas('webhook_events', [
            'provider_event_id' => 'wamid.webhook-test-001',
            'status' => 'received',
        ]);
    }

    public function test_duplicate_inbound_message_does_not_dispatch_another_job(): void
    {
        Queue::fake();

        $payload = [
            'provider_message_id' => 'wamid.webhook-test-002',
            'sender_phone_e164' => '+255712345679',
            'text' => 'Habari',
        ];

        $headers = [
            'X-Gateway-Secret' => 'test-only-secret-0123456789abcdef',
        ];

        $this->postJson(
            '/api/internal/whatsapp/inbound',
            $payload,
            $headers,
        )->assertCreated();

        $this->postJson(
            '/api/internal/whatsapp/inbound',
            $payload,
            $headers,
        )->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, Message::count());
        $this->assertSame(1, WebhookEvent::count());

        Queue::assertPushed(ProcessInboundMessage::class, 1);
    }

    public function test_request_without_gateway_secret_is_rejected(): void
    {
        Queue::fake();

        $this->postJson('/api/internal/whatsapp/inbound', [
            'provider_message_id' => 'wamid.webhook-test-003',
            'sender_phone_e164' => '+255712345680',
            'text' => 'Hello',
        ])->assertUnauthorized();

        $this->assertSame(0, Message::count());

        Queue::assertNothingPushed();
    }
}

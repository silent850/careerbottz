<?php
namespace Tests\Feature;

use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboundEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp_gateway.secret' => 'test-gateway-secret',
        ]);
    }

    public function test_request_without_gateway_secret_is_rejected(): void
    {
        $this->postJson('/api/internal/whatsapp/inbound', [
            'provider_message_id' => 'wamid.endpoint-001',
            'sender_phone_e164' => '+255712345678',
            'text' => 'Hello',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_valid_inbound_request_is_saved(): void
    {
        $this->withHeader(
            'X-Gateway-Secret',
            'test-gateway-secret'
        )->postJson('/api/internal/whatsapp/inbound', [
            'provider_message_id' => 'wamid.endpoint-002',
            'sender_phone_e164' => '+255712345678',
            'text' => 'Hello CareerBotTz',
        ])->assertCreated()
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_invalid_payload_returns_unprocessable_entity(): void
    {
        $this->withHeader(
            'X-Gateway-Secret',
            'test-gateway-secret'
        )->postJson('/api/internal/whatsapp/inbound', [
            'provider_message_id' => 'wamid.endpoint-003',
            'sender_phone_e164' => '0712345678',
            'text' => 'Hello',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_duplicate_request_does_not_create_duplicate_records(): void
    {
        $payload = [
            'provider_message_id' => 'wamid.endpoint-004',
            'sender_phone_e164' => '+255712345679',
            'text' => 'Habari',
        ];

        $this->withHeader(
            'X-Gateway-Secret',
            'test-gateway-secret'
        )->postJson('/api/internal/whatsapp/inbound', $payload)
            ->assertCreated();

        $this->withHeader(
            'X-Gateway-Secret',
            'test-gateway-secret'
        )->postJson('/api/internal/whatsapp/inbound', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('webhook_events', 1);
    }
}

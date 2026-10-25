<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\User;
use App\Services\WhatsApp\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InboundMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_valid_inbound_message(): void
    {
        $result = app(InboundMessageService::class)->receive([
            'provider_message_id' => 'wamid.test-001',
            'sender_phone_e164' => '+255712345678',
            'text' => 'Hello CareerBotTz',
        ]);

        $this->assertFalse($result['duplicate']);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'whatsapp',
            'provider_event_id' => 'wamid.test-001',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('messages', [
            'provider' => 'whatsapp',
            'provider_message_id' => 'wamid.test-001',
            'direction' => 'inbound',
            'content' => 'Hello CareerBotTz',
        ]);

        $this->assertDatabaseHas('users', [
            'phone_e164' => '+255712345678',
        ]);
    }

    public function test_duplicate_event_does_not_create_another_message(): void
    {
        $service = app(InboundMessageService::class);

        $payload = [
            'provider_message_id' => 'wamid.test-002',
            'sender_phone_e164' => '+255712345679',
            'text' => 'Habari',
        ];

        $first = $service->receive($payload);
        $second = $service->receive($payload);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, Message::count());
        $this->assertSame(
            1,
            User::where('phone_e164', '+255712345679')->count()
        );
    }

    public function test_it_rejects_an_invalid_sender_phone_number(): void
    {
        $this->expectException(ValidationException::class);

        app(InboundMessageService::class)->receive([
            'provider_message_id' => 'wamid.test-003',
            'sender_phone_e164' => '0712345678',
            'text' => 'Hello',
        ]);
    }
}

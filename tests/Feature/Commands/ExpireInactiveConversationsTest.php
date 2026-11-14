<?php

namespace Tests\Feature\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpireInactiveConversationsTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(
        string $status = 'active',
        int $inactiveMinutes = 0,
    ): Conversation {
        $user = User::factory()->create([
            'phone_e164' => fake()->unique()->e164PhoneNumber(),
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => 'personal_details',
            'status' => $status,
        ]);

        DB::table('conversations')
            ->where('id', $conversation->id)
            ->update([
                'updated_at' => now()->subMinutes($inactiveMinutes),
            ]);

        return $conversation->fresh();
    }

    public function test_it_does_not_expire_a_conversation_inactive_for_less_than_one_hour(): void
    {
        $conversation = $this->createConversation(
            inactiveMinutes: 59,
        );

        $exitCode = Artisan::call('conversations:expire-inactive');

        $this->assertSame(0, $exitCode);

        $this->assertSame(
            'active',
            $conversation->fresh()->status,
        );
    }

    public function test_it_expires_a_conversation_inactive_for_exactly_one_hour(): void
    {
        $conversation = $this->createConversation(
            inactiveMinutes: 60,
        );

        $exitCode = Artisan::call('conversations:expire-inactive');

        $this->assertSame(0, $exitCode);

        $this->assertSame(
            'expired',
            $conversation->fresh()->status,
        );
    }

    public function test_it_does_not_expire_paused_or_cancelled_conversations(): void
    {
        $paused = $this->createConversation(
            status: 'paused',
            inactiveMinutes: 120,
        );

        $cancelled = $this->createConversation(
            status: 'cancelled',
            inactiveMinutes: 120,
        );

        Artisan::call('conversations:expire-inactive');

        $this->assertSame('paused', $paused->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_expiration_preserves_existing_conversation_messages(): void
    {
        $conversation = $this->createConversation(
            inactiveMinutes: 120,
        );

        $message = Message::query()->create([
            'user_id' => $conversation->user_id,
            'conversation_id' => $conversation->id,
            'provider' => 'whatsapp',
            'provider_message_id' => 'wamid.expiration-test-001',
            'direction' => 'inbound',
            'content' => 'My personal details',
            'status' => 'processed',
            'received_at' => now()->subMinutes(120),
            'processed_at' => now()->subMinutes(119),
        ]);

        Artisan::call('conversations:expire-inactive');

        $this->assertSame(
            'expired',
            $conversation->fresh()->status,
        );

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'content' => 'My personal details',
        ]);
    }
}

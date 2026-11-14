<?php

namespace Tests\Feature\Commands;

use App\Models\Message;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecoverStaleOutboundMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function createOutbound(string $status, string $suffix): OutboundMessage
    {
        $user = User::factory()->create([
            'phone_e164' => '+25571234' . str_pad($suffix, 4, '0', STR_PAD_LEFT),
        ]);

        $message = Message::query()->create([
            'user_id' => $user->id,
            'provider' => 'whatsapp',
            'provider_message_id' => 'wamid.recovery-' . $suffix,
            'direction' => 'inbound',
            'content' => 'Hello',
            'status' => 'received',
            'received_at' => now(),
        ]);

        return OutboundMessage::query()->create([
            'inbound_message_id' => $message->id,
            'user_id' => $user->id,
            'provider' => 'whatsapp',
            'idempotency_key' => hash('sha256', 'recovery:' . $suffix),
            'recipient_phone_e164' => $user->phone_e164,
            'content' => 'Hello from CareerBotTz',
            'status' => $status,
            'attempt_count' => $status === 'sending' ? 1 : 0,
        ]);
    }

    public function test_it_marks_stale_sending_records_unknown_without_resending(): void
    {
        $stale = $this->createOutbound('sending', '1001');

        DB::table('outbound_messages')
            ->where('id', $stale->id)
            ->update(['updated_at' => now()->subMinutes(10)]);

        $exitCode = Artisan::call('outbound:recover-stale', [
            '--minutes' => 5,
        ]);

        $this->assertSame(0, $exitCode);

        $stale->refresh();

        $this->assertSame('unknown', $stale->status);
        $this->assertNotNull($stale->unknown_at);
        $this->assertStringContainsString(
            'Reconcile delivery',
            $stale->last_error,
        );
        $this->assertSame(1, $stale->attempt_count);
        $this->assertNull($stale->sent_at);
        $this->assertNull($stale->provider_message_id);
    }

    public function test_it_leaves_fresh_sending_records_unchanged(): void
    {
        $fresh = $this->createOutbound('sending', '1002');

        $exitCode = Artisan::call('outbound:recover-stale', [
            '--minutes' => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('sending', $fresh->fresh()->status);
        $this->assertNull($fresh->fresh()->unknown_at);
    }

    public function test_it_does_not_change_sent_or_already_unknown_records(): void
    {
        $sent = $this->createOutbound('sent', '1003');
        $unknown = $this->createOutbound('unknown', '1004');

        DB::table('outbound_messages')
            ->whereIn('id', [$sent->id, $unknown->id])
            ->update(['updated_at' => now()->subMinutes(10)]);

        $originalUnknownError = 'Previously marked unknown.';

        DB::table('outbound_messages')
            ->where('id', $unknown->id)
            ->update([
                'last_error' => $originalUnknownError,
                'unknown_at' => now()->subMinutes(8),
            ]);

        $exitCode = Artisan::call('outbound:recover-stale', [
            '--minutes' => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('sent', $sent->fresh()->status);
        $this->assertSame('unknown', $unknown->fresh()->status);
        $this->assertSame(
            $originalUnknownError,
            $unknown->fresh()->last_error,
        );
    }

    public function test_it_rejects_a_non_positive_recovery_threshold(): void
    {
        $exitCode = Artisan::call('outbound:recover-stale', [
            '--minutes' => 0,
        ]);

        $this->assertSame(1, $exitCode);
    }
}

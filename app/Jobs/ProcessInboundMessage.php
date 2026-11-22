<?php
namespace App\Jobs;

use App\Models\Message;
use App\Models\OutboundMessage;
use App\Models\WebhookEvent;
use App\Services\Conversation\ConversationEngine;
use App\Services\WhatsApp\WhatsAppGatewayClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessInboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    private const CLAIM_TIMEOUT_MINUTES = 5;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(
        ConversationEngine $conversationEngine,
        WhatsAppGatewayClient $gatewayClient,
    ): void {
        $claimed = DB::transaction(function (): bool {
            $message = Message::query()
                ->lockForUpdate()
                ->findOrFail($this->messageId);

            if (
                $message->direction !== 'inbound'
                || $message->status === 'processed'
                || $message->processed_at !== null
            ) {
                return false;
            }

            if (
                $message->status === 'processing'
                && $message->updated_at !== null
                && $message->updated_at->gt(
                    now()->subMinutes(self::CLAIM_TIMEOUT_MINUTES)
                )
            ) {
                return false;
            }

            $message->status = 'processing';
            $message->save();

            return true;
        });

        if (! $claimed) {
            return;
        }

        try {
            /*
             * Load the inbound message and user before preparing the conversation.
             */
            $message = Message::query()
                ->with('user')
                ->findOrFail($this->messageId);

            if (
                $message->direction !== 'inbound'
                || $message->status !== 'processing'
                || $message->processed_at !== null
            ) {
                throw new RuntimeException(
                    'Inbound message is no longer eligible for processing.'
                );
            }

            if (
                ! is_string($message->user->phone_e164)
                || $message->user->phone_e164 === ''
            ) {
                throw new RuntimeException(
                    'Cannot process an inbound message without a phone number.'
                );
            }

            /*
             * Check for an existing outbound intent BEFORE calling Gemini.
             *
             * An unknown, sending, sent or failed outbound record must never
             * trigger another AI preparation or automatic delivery attempt.
             *
             * Pending records may continue through the existing delivery flow.
             */
            $existingOutbound = OutboundMessage::query()
                ->where('inbound_message_id', $message->id)
                ->first();

          if (
    $existingOutbound !== null
    && $existingOutbound->status !== 'pending'
) {
    DB::transaction(function (): void {
        $message = Message::query()
            ->lockForUpdate()
            ->find($this->messageId);

        if (
            $message !== null
            && $message->status === 'processing'
            && $message->processed_at === null
        ) {
            $message->status = 'received';
            $message->save();
        }
    });

    return;
}

            if ($existingOutbound !== null) {
                /*
                 * An existing pending outbound intent can proceed to delivery.
                 * Do not regenerate its content.
                 */
                $outbound = $existingOutbound;
            } else {
                /*
                 * Prepare the conversation outside the database transaction.
                 * Gemini/API processing happens here.
                 */
                $prepared = $conversationEngine->prepare(
                    $message->user,
                    $message->content,
                    $message,
                );

                /*
                 * Commit conversation changes and create the outbound intent
                 * atomically.
                 */
                $outbound = DB::transaction(function () use (
                    $prepared,
                    $conversationEngine,
                ): OutboundMessage {
                    $message = Message::query()
                        ->with('user')
                        ->lockForUpdate()
                        ->findOrFail($this->messageId);

                    if (
                        $message->direction !== 'inbound'
                        || $message->status !== 'processing'
                        || $message->processed_at !== null
                    ) {
                        throw new RuntimeException(
                            'Inbound message is no longer eligible for processing.'
                        );
                    }

                    if (
                        ! is_string($message->user->phone_e164)
                        || $message->user->phone_e164 === ''
                    ) {
                        throw new RuntimeException(
                            'Cannot process an inbound message without a phone number.'
                        );
                    }

                    /*
                     * Recheck under lock to protect against concurrent workers.
                     */
                    $existing = OutboundMessage::query()
                        ->where('inbound_message_id', $message->id)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        return $existing;
                    }

                    /*
                     * Commit the prepared conversation changes.
                     */
                    $response = $conversationEngine->commit(
                        $prepared,
                        $message,
                    );

                    /*
                     * Save the outbound intent in the same transaction.
                     */
                    return OutboundMessage::query()->create([
                        'inbound_message_id' => $message->id,
                        'user_id' => $message->user_id,
                        'provider' => $message->provider,
                        'idempotency_key' => hash(
                            'sha256',
                            $message->provider . ':'
                                . $message->provider_message_id
                        ),
                        'recipient_phone_e164' => $message->user->phone_e164,
                        'content' => $response->reply,
                        'status' => 'pending',
                    ]);
                });
            }

            /*
             * Only pending records may be sent. Never automatically resend
             * records marked sending, unknown, sent, or failed.
             */
            $maySend = DB::transaction(function () use ($outbound): bool {
                $locked = OutboundMessage::query()
                    ->lockForUpdate()
                    ->findOrFail($outbound->id);

                if ($locked->status !== 'pending') {
                    return false;
                }

                $locked->status = 'sending';
                $locked->attempt_count++;
                $locked->last_error = null;
                $locked->save();

                return true;
            });

          if (! $maySend) {
    DB::transaction(function (): void {
        $message = Message::query()
            ->lockForUpdate()
            ->find($this->messageId);

        if (
            $message !== null
            && $message->status === 'processing'
            && $message->processed_at === null
        ) {
            $message->status = 'received';
            $message->save();
        }
    });

    return;
}

            try {
                $delivery = $gatewayClient->sendTextMessage(
                    $outbound->recipient_phone_e164,
                    $outbound->content,
                );
            } catch (Throwable $exception) {
                DB::transaction(function () use (
                    $outbound,
                    $exception
                ): void {
                    $locked = OutboundMessage::query()
                        ->lockForUpdate()
                        ->findOrFail($outbound->id);

                    if ($locked->status === 'sending') {
                        $locked->status = 'unknown';
                        $locked->unknown_at = now();
                        $locked->last_error = mb_substr(
                            $exception->getMessage(),
                            0,
                            4000,
                        );
                        $locked->save();
                    }
                });

                throw $exception;
            }

            DB::transaction(function () use (
                $outbound,
                $delivery
            ): void {
                $lockedOutbound = OutboundMessage::query()
                    ->lockForUpdate()
                    ->findOrFail($outbound->id);

                $lockedMessage = Message::query()
                    ->lockForUpdate()
                    ->findOrFail($this->messageId);

                $lockedOutbound->status = 'sent';
                $lockedOutbound->provider_message_id =
                    $delivery['provider_message_id'];
                $lockedOutbound->sent_at = now();
                $lockedOutbound->last_error = null;
                $lockedOutbound->save();

                $lockedMessage->status = 'processed';
                $lockedMessage->processed_at = now();
                $lockedMessage->save();

                WebhookEvent::query()
                    ->where('provider', $lockedMessage->provider)
                    ->where(
                        'provider_event_id',
                        $lockedMessage->provider_message_id,
                    )
                    ->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);
            });
        } catch (Throwable $exception) {
            DB::transaction(function (): void {
                $message = Message::query()
                    ->lockForUpdate()
                    ->find($this->messageId);

                if (
                    $message !== null
                    && $message->status === 'processing'
                    && $message->processed_at === null
                ) {
                    $message->status = 'received';
                    $message->save();
                }
            });

            throw $exception;
        }
    }
}
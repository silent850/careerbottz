<?php

namespace App\Services\WhatsApp;

use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsApp\WhatsAppIdentityService;

class InboundMessageService
{
    public function __construct(
        private readonly WhatsAppIdentityService $identityService,
    ) {
    }

    public function receive(array $payload): array
    {
        $validated = Validator::make($payload, [
            'provider_message_id' => ['required', 'string', 'max:191'],
            'sender_phone_e164' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],
            'text' => ['required', 'string', 'max:10000'],
        ])->validate();

        $provider = 'whatsapp';
        $providerMessageId = $validated['provider_message_id'];

        try {
            return DB::transaction(function () use (
                $validated,
                $provider,
                $providerMessageId
            ): array {
                $event = WebhookEvent::create([
                    'provider' => $provider,
                    'provider_event_id' => $providerMessageId,
                    'event_type' => 'message.inbound',
                    'status' => 'received',
                    'payload' => [
                        'provider_message_id' => $providerMessageId,
                        'sender_phone_e164' => $validated['sender_phone_e164'],
                        'text' => $validated['text'],
                    ],
                    'received_at' => now(),
                ]);

                $user = $this->identityService->findOrCreate(
                    $validated['sender_phone_e164']
                );

                $message = Message::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_message_id' => $providerMessageId,
                    'direction' => 'inbound',
                    'content' => $validated['text'],
                    'status' => 'received',
                    'received_at' => now(),
                ]);

                return [
                    'duplicate' => false,
                    'event_id' => $event->id,
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                ];
            });
        } catch (QueryException $exception) {
            $existingEvent = WebhookEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $providerMessageId)
                ->first();

            if ($existingEvent !== null) {
                return [
                    'duplicate' => true,
                    'event_id' => $existingEvent->id,
                    'message_id' => Message::query()
                        ->where('provider', $provider)
                        ->where('provider_message_id', $providerMessageId)
                        ->value('id'),
                ];
            }

            throw $exception;
        }
    }
}

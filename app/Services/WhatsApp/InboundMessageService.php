<?php

namespace App\Services\WhatsApp;

use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InboundMessageService
{
    public function __construct(
        private readonly WhatsAppIdentityService $identityService,
    ) {
    }

    public function receive(
        array $payload,
        ?UploadedFile $attachment = null,
    ): array {
        $validationData = array_merge($payload, [
            'attachment' => $attachment,
        ]);

        $validator = Validator::make($validationData, [
            'provider_message_id' => [
                'required',
                'string',
                'max:191',
            ],
            'sender_phone_e164' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
            ],
            'text' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
            ],
        ]);

        $validator->after(function ($validator) use ($payload, $attachment) {
            $text = $payload['text'] ?? '';

            if (
                (! is_string($text) || trim($text) === '')
                && $attachment === null
            ) {
                $validator->errors()->add(
                    'text',
                    'A message must contain text or an attachment.'
                );
            }
        });

        $validated = $validator->validate();

        $provider = 'whatsapp';
        $providerMessageId = $validated['provider_message_id'];
        $text = $validated['text'] ?? '';
        $attachmentPath = null;

        if ($attachment !== null) {
            $attachmentPath = $attachment->store(
                'whatsapp-inbound',
                'local'
            );

            if ($attachmentPath === false) {
                throw new RuntimeException(
                    'Unable to store the inbound WhatsApp attachment.'
                );
            }
        }

        try {
            return DB::transaction(function () use (
                $validated,
                $provider,
                $providerMessageId,
                $text,
                $attachment,
                $attachmentPath
            ): array {
                $attachmentMetadata = $attachment === null
                    ? []
                    : [
                        'attachment_path' => $attachmentPath,
                        'attachment_mime_type' => $attachment->getMimeType(),
                        'attachment_original_name' => mb_substr(
                            $attachment->getClientOriginalName(),
                            0,
                            255
                        ),
                        'attachment_size' => $attachment->getSize(),
                    ];

                $event = WebhookEvent::create([
                    'provider' => $provider,
                    'provider_event_id' => $providerMessageId,
                    'event_type' => 'message.inbound',
                    'status' => 'received',
                    'payload' => [
                        'provider_message_id' => $providerMessageId,
                        'sender_phone_e164' => $validated['sender_phone_e164'],
                        'text' => $text,
                        'attachment' => $attachment === null
                            ? null
                            : [
                                'mime_type' => $attachmentMetadata['attachment_mime_type'],
                                'original_name' => $attachmentMetadata['attachment_original_name'],
                                'size' => $attachmentMetadata['attachment_size'],
                            ],
                    ],
                    'received_at' => now(),
                ]);

                $user = $this->identityService->findOrCreate(
                    $validated['sender_phone_e164']
                );

                $message = Message::create(array_merge([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_message_id' => $providerMessageId,
                    'direction' => 'inbound',
                    'content' => $text,
                    'status' => 'received',
                    'received_at' => now(),
                ], $attachmentMetadata));

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
                $this->deleteStoredAttachment($attachmentPath);

                return [
                    'duplicate' => true,
                    'event_id' => $existingEvent->id,
                    'message_id' => Message::query()
                        ->where('provider', $provider)
                        ->where('provider_message_id', $providerMessageId)
                        ->value('id'),
                ];
            }

            $this->deleteStoredAttachment($attachmentPath);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteStoredAttachment($attachmentPath);

            throw $exception;
        }
    }

    private function deleteStoredAttachment(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}

<?php
namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppGatewayClient
{
    /**
     * Retrieve the current WhatsApp pairing state from the gateway.
     *
     * @return array{
     *     status: string,
     *     qr: string|null,
     *     updatedAt: string
     * }
     */
    public function getPairingStatus(): array
    {
        $baseUrl = rtrim(
            (string) config('services.whatsapp_gateway.base_url'),
            '/'
        );

        $secret = (string) config('services.whatsapp_gateway.secret');

        if ($baseUrl === '' || $secret === '') {
            throw new RuntimeException(
                'WhatsApp gateway configuration is incomplete.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Gateway-Secret' => $secret,
                ])
                ->connectTimeout(3)
                ->timeout(5)
                ->get($baseUrl . '/internal/pairing-status');
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not connect to the WhatsApp gateway.',
                0,
                $exception
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'WhatsApp gateway returned HTTP '
                . $response->status() . '.'
            );
        }

        $data = $response->json();

        if (
            ! is_array($data)
            || ! isset($data['status'], $data['updatedAt'])
            || ! array_key_exists('qr', $data)
        ) {
            throw new RuntimeException(
                'WhatsApp gateway returned an invalid pairing status.'
            );
        }

        if (
            ! is_string($data['status'])
            || ! is_string($data['updatedAt'])
            || (
                $data['qr'] !== null
                && ! is_string($data['qr'])
            )
        ) {
            throw new RuntimeException(
                'WhatsApp gateway returned invalid pairing data.'
            );
        }

        return [
            'status' => $data['status'],
            'qr' => $data['qr'],
            'updatedAt' => $data['updatedAt'],
        ];
    }

    /**
     * Send a text message through the authenticated WhatsApp gateway.
     *
     * @return array{success: true, provider_message_id: string}
     */
    public function sendTextMessage(
        string $phoneE164,
        string $text,
    ): array {
        if (
            ! preg_match('/^\+[1-9]\d{7,14}$/', $phoneE164)
            || trim($text) === ''
            || mb_strlen(trim($text)) > 4000
        ) {
            throw new RuntimeException(
                'Invalid WhatsApp recipient or message text.'
            );
        }

        $baseUrl = rtrim(
            (string) config('services.whatsapp_gateway.base_url'),
            '/'
        );

        $secret = (string) config('services.whatsapp_gateway.secret');

        if ($baseUrl === '' || $secret === '') {
            throw new RuntimeException(
                'WhatsApp gateway configuration is incomplete.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Gateway-Secret' => $secret,
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->post($baseUrl . '/internal/send-message', [
                    'phone_e164' => $phoneE164,
                    'text' => trim($text),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not confirm delivery through the WhatsApp gateway.',
                0,
                $exception
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'WhatsApp gateway returned HTTP '
                . $response->status() . '.'
            );
        }

        $data = $response->json();

        if (
            ! is_array($data)
            || ($data['success'] ?? false) !== true
            || ! isset($data['provider_message_id'])
            || ! is_string($data['provider_message_id'])
            || $data['provider_message_id'] === ''
        ) {
            throw new RuntimeException(
                'WhatsApp gateway returned an invalid send response.'
            );
        }

        return [
            'success' => true,
            'provider_message_id' => $data['provider_message_id'],
        ];
    }
}

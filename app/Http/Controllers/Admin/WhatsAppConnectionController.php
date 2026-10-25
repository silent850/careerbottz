<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppGatewayClient;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class WhatsAppConnectionController extends Controller
{
    public function status(
        WhatsAppGatewayClient $gateway
    ): JsonResponse {
        try {
            $pairing = $gateway->getPairingStatus();

            return response()->json([
                'gateway' => 'reachable',
                'whatsapp' => $pairing['status'] ?? 'unknown',
                'qr' => $pairing['qr'] ?? null,
                'updated_at' => $pairing['updatedAt'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'gateway' => 'unavailable',
                'whatsapp' => 'unknown',
                'qr' => null,
                'message' => 'The WhatsApp gateway is currently unavailable.',
            ], 503);
        }
    }
}

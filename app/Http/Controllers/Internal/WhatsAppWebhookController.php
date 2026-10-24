<?php

namespace App\Http\Controllers\Internal;

use App\Services\WhatsApp\InboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppWebhookController
{
    public function __invoke(
        Request $request,
        InboundMessageService $inboundMessageService,
    ): JsonResponse {
        try {
            $result = $inboundMessageService->receive(
                $request->all()
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Invalid inbound message payload.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'message' => $result['duplicate']
                ? 'Inbound message already received.'
                : 'Inbound message received.',
            'data' => $result,
        ], $result['duplicate'] ? 200 : 201);
    }
}

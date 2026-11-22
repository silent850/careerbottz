<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessage;
use App\Services\WhatsApp\InboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        InboundMessageService $inboundMessageService,
    ): JsonResponse {
        try {
            $result = $inboundMessageService->receive(
                $request->input(),
                $request->file('attachment'),
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Invalid inbound message payload.',
                'errors' => $exception->errors(),
            ], 422);
        }

        if (! $result['duplicate']) {
            ProcessInboundMessage::dispatch(
                (int) $result['message_id']
            );
        }

        return response()->json([
            'message' => $result['duplicate']
                ? 'Inbound message already received.'
                : 'Inbound message received.',
            'data' => $result,
        ], $result['duplicate'] ? 200 : 201);
    }
}

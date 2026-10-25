<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;



class AuthenticateWhatsAppGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.whatsapp_gateway.secret');
        $providedSecret = $request->header('X-Gateway-Secret');

        if (
            ! is_string($expectedSecret)
            || $expectedSecret === ''
            || ! is_string($providedSecret)
            || ! hash_equals($expectedSecret, $providedSecret)
        ) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}

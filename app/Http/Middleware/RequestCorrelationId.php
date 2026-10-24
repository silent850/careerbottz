<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationId
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER);

        if (
            ! is_string($requestId)
            || ! preg_match('/\A[a-zA-Z0-9._-]{1,128}\z/', $requestId)
        ) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}

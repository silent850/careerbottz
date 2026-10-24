<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Internal\WhatsAppWebhookController;
use App\Http\Middleware\AuthenticateWhatsAppGateway;

Route::post('/internal/whatsapp/inbound', WhatsAppWebhookController::class)
    ->middleware(AuthenticateWhatsAppGateway::class);

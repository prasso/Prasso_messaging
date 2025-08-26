<?php

use Illuminate\Support\Facades\Route;
use Prasso\Messaging\Http\Controllers\Api\TwilioWebhookController;
use Prasso\Messaging\Http\Controllers\Api\TwilioStatusWebhookController;
use Prasso\Messaging\Http\Middleware\VerifyTwilioSignature;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Twilio Inbound Message Webhook (secured with signature validation)
Route::post('/webhooks/twilio', [TwilioWebhookController::class, 'handleIncomingMessage'])
    ->middleware(VerifyTwilioSignature::class)
    ->name('webhooks.twilio');

// Twilio Status Callback Webhook (DLR)
Route::post('/webhooks/twilio/status', [TwilioStatusWebhookController::class, 'handleStatus'])
    ->middleware(VerifyTwilioSignature::class)
    ->name('webhooks.twilio.status');

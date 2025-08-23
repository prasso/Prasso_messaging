<?php

use Illuminate\Support\Facades\Route;
use Prasso\Messaging\Http\Controllers\Api\TwilioWebhookController;

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

// Twilio Webhook Endpoint
Route::post('/webhooks/twilio', [TwilioWebhookController::class, 'handleIncomingMessage'])
    ->name('webhooks.twilio');

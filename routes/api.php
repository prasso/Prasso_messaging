<?php

use Illuminate\Support\Facades\Route;    
use Prasso\Messaging\Http\Controllers\Api\GuestController;
use Prasso\Messaging\Http\Controllers\Api\MessageController;
use Prasso\Messaging\Http\Controllers\Api\CampaignController;
use Prasso\Messaging\Http\Controllers\Api\EngagementController;
use Prasso\Messaging\Http\Controllers\Api\WorkflowController;
use Prasso\Messaging\Http\Controllers\Api\GuestMessageController;
use Prasso\Messaging\Http\Controllers\Api\AlertController;
use Prasso\Messaging\Http\Controllers\Api\EventController;
use Prasso\Messaging\Http\Controllers\Api\VoiceBroadcastController;

Route::middleware(['api','auth:sanctum'])->prefix('api')->group(function () {

//GuestController;
Route::get('/guests', [GuestController::class, 'index']);
Route::post('/guests', [GuestController::class, 'store']);
Route::get('/guests/{id}', [GuestController::class, 'show']);
Route::put('/guests/{id}', [GuestController::class, 'update']);
Route::delete('/guests/{id}', [GuestController::class, 'destroy']);
Route::post('/guests/{id}/convert', [GuestController::class, 'convert']);
Route::post('/guests/{id}/engage', [GuestController::class, 'engage']);


//MessageController;
Route::get('/messages', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);
Route::get('/messages/{id}', [MessageController::class, 'show']);
Route::put('/messages/{id}', [MessageController::class, 'update']);
Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
Route::post('/messages/send', [MessageController::class, 'send']);

//CampaignController;
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::post('/campaigns', [CampaignController::class, 'store']);
Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);
Route::post('/campaigns/{id}/messages', [CampaignController::class, 'addMessage']);
Route::post('/campaigns/{id}/launch', [CampaignController::class, 'launch']);

#EngagementController
Route::get('/engagements', [EngagementController::class, 'index']);
Route::post('/engagements', [EngagementController::class, 'store']);
Route::get('/engagements/{id}', [EngagementController::class, 'show']);
Route::put('/engagements/{id}', [EngagementController::class, 'update']);
Route::delete('/engagements/{id}', [EngagementController::class, 'destroy']);
Route::post('/engagements/{id}/responses', [EngagementController::class, 'recordResponse']);

#WorkflowController;
Route::get('/workflows', [WorkflowController::class, 'index']);
Route::post('/workflows', [WorkflowController::class, 'store']);
Route::get('/workflows/{id}', [WorkflowController::class, 'show']);
Route::put('/workflows/{id}', [WorkflowController::class, 'update']);
Route::delete('/workflows/{id}', [WorkflowController::class, 'destroy']);
Route::post('/workflows/{id}/steps', [WorkflowController::class, 'addSteps']);
Route::post('/workflows/{id}/start', [WorkflowController::class, 'start']);

#GuestMessageController;
Route::get('/guest-messages', [GuestMessageController::class, 'index']);
Route::post('/guest-messages', [GuestMessageController::class, 'store']);
Route::get('/guest-messages/{id}', [GuestMessageController::class, 'show']);
Route::put('/guest-messages/{id}', [GuestMessageController::class, 'update']);
Route::delete('/guest-messages/{id}', [GuestMessageController::class, 'destroy']);

#AlertController;
Route::post('/alerts/emergency', [AlertController::class, 'sendEmergencyAlert']);
Route::post('/alerts/news', [AlertController::class, 'sendNewsUpdate']);

#EventController;
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::put('/events/{id}', [EventController::class, 'update']);
Route::delete('/events/{id}', [EventController::class, 'destroy']);
Route::post('/events/{id}/reminders', [EventController::class, 'scheduleReminders']);

#VoiceBroadcastController;
Route::middleware(['api','auth:sanctum'])->post('/voice-broadcasts/send', [VoiceBroadcastController::class, 'send']);


});
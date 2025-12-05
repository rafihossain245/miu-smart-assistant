<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

// Public chat endpoints
Route::post('/chat', [ChatController::class, 'chat'])->name('api.chat');
Route::get('/chatbot/{chatbot_id}', [ChatController::class, 'getChatbotInfo'])->name('api.chatbot.info');

Route::get('/conversation/{session_id}/messages', [ChatController::class, 'getConversationMessages']);
<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

// Public chat endpoints
Route::post('/chat', [ChatController::class, 'chat'])->name('api.chat');
Route::get('/chatbot/{chatbot_id}', [ChatController::class, 'getChatbotInfo'])->name('api.chatbot.info');
Route::get('/widget-config/{chatbot_id}', [ChatController::class, 'getChatbotInfo'])->name('api.widget.config'); // Alias for widget
Route::get('/conversation/{session_id}/messages', [ChatController::class, 'getConversationMessages']);
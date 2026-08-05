<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

// Public chat endpoints
Route::post('/chat', [ChatController::class, 'chat'])->name('api.chat');
Route::post('/feedback', [ChatController::class, 'feedback'])->name('api.feedback');
Route::post('/correction', [ChatController::class, 'correction'])->name('api.correction');
Route::get('/chatbot/{chatbot_id}', [ChatController::class, 'getChatbotInfo'])->name('api.chatbot.info');
Route::get('/conversation/{session_id}/messages', [ChatController::class, 'getConversationMessages']);
// Route::get('/widget-config/{chatbot_id}', [ChatController::class, 'getChatbotInfo'])->name('api.widget.config'); // Alias for widget
// Route::get('/broadcast-config', [ChatController::class, 'getBroadcastConfig'])->name('api.broadcast.config');
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chatbot channel authorization - user can only listen to their own chatbots
Broadcast::channel('chatbot.{chatbotId}', function ($user, $chatbotId) {
    return $user->chatbots()->where('id', $chatbotId)->exists();
});

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Chatbot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    public function test_chat_api_endpoint()
    {
        // Create user and chatbot
        $user = User::factory()->create();
        $chatbot = $user->chatbots()->create([
            'name' => 'Test Bot',
            'description' => 'Test Description',
            'welcome_message' => 'Hello!',
            'is_active' => true,
        ]);

        // Test chat API
        $response = $this->post('/api/chat', [
            'message' => 'hello',
            'chatbot_id' => $chatbot->id,
            'session_id' => 'test_session'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reply',
            'sources'
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['reply']);
        $this->assertIsArray($data['sources']);
    }
}
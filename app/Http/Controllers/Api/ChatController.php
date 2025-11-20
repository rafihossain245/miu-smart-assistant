<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Services\RAGService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    protected RAGService $ragService;

    public function __construct(RAGService $ragService)
    {
        $this->ragService = $ragService;
    }

    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'chatbot_id' => 'required|exists:chatbots,id',
            'session_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            // Find the chatbot
            $chatbot = Chatbot::findOrFail($request->chatbot_id);

            // Check if chatbot is active
            if (!$chatbot->is_active) {
                return response()->json([
                    'error' => 'Chatbot is not active',
                ], 403);
            }

            // Generate response using RAG service
            $response = $this->ragService->generateResponse(
                question: $request->message,
                chatbotId: $chatbot->id,
                sessionId: $request->session_id,
                ipAddress: $request->ip()
            );

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request',
                'message' => config('app.debug') ? $e->getMessage() : 'Please try again later',
            ], 500);
        }
    }

    public function getChatbotInfo(string $chatbotId): JsonResponse
    {
        try {
            $chatbot = Chatbot::with('user:id,name')
                ->where('id', $chatbotId)
                ->where('is_active', true)
                ->firstOrFail();

            return response()->json([
                'id' => $chatbot->id,
                'name' => $chatbot->name,
                'description' => $chatbot->description,
                'welcome_message' => $chatbot->welcome_message,
                'appearance' => $chatbot->appearance,
                'owner' => $chatbot->user->name,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Chatbot not found or inactive',
            ], 404);
        }
    }
}

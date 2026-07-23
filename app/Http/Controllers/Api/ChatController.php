<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\RAGService;
use App\Services\TenantDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            'tenant_id' => 'nullable|string|max:255',
            'query_mode' => 'nullable|in:general,sql',
            'sql_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $chatbot = Chatbot::findOrFail($request->chatbot_id);

            if (!$chatbot->is_active) {
                return response()->json([
                    'error' => 'Chatbot is not active',
                ], 403);
            }

            $response = $this->ragService->generateResponse(
                question: $request->message,
                chatbotId: $chatbot->id,
                sessionId: $request->session_id,
                ipAddress: $request->ip()
            );

            return response()->json($response);

        } catch (\Throwable $e) {
            Log::error('Chat API Error: ' . $e->getMessage(), [
                'chatbot_id' => $request->chatbot_id,
                'session_id' => $request->session_id,
                'exception' => $e,
            ]);
                                                
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

    public function getConversationMessages(string $sessionId): JsonResponse
    {
        $conversation = Conversation::where('session_id', $sessionId)
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->first();

        if (!$conversation) {
            return response()->json(['messages' => []]);
        }
        
        return response()->json([
            'messages' => $conversation->messages->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'isBot' => $msg->is_bot,
                    'timestamp' => $msg->created_at,
                    'sources' => $msg->sources ?? [],
                    'learningDataId' => $msg->learning_data_id ?? null,
                    'feedback' => null,
                    'showContactInfo' => false
                ];
            })
        ]);
    }
}

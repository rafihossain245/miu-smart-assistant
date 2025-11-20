<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_chatbots' => $user->chatbots()->count(),
            'active_chatbots' => $user->chatbots()->where('is_active', true)->count(),
            'total_conversations' => Conversation::whereHas('chatbot', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->count(),
            'conversations_today' => Conversation::whereHas('chatbot', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->whereDate('created_at', today())->count(),
        ];

        $recentChatbots = $user->chatbots()
            ->withCount(['sources', 'conversations'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentChatbots' => $recentChatbots,
        ]);
    }
}

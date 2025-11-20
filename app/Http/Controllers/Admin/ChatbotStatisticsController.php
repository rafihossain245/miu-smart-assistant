<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\TokenUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatbotStatisticsController extends Controller
{
    /**
     * Display chatbot statistics overview
     */
    public function index()
    {
        $chatbots = Chatbot::with(['tokenUsage', 'conversations', 'sources'])
            ->withCount(['conversations', 'sources'])
            ->get()
            ->map(function ($chatbot) {
                $tokenStats = $chatbot->tokenUsage()
                    ->selectRaw('
                        SUM(total_tokens) as total_tokens,
                        SUM(cost) as total_cost,
                        COUNT(*) as total_api_calls,
                        AVG(total_tokens) as avg_tokens_per_call
                    ')
                    ->first();

                $todayStats = $chatbot->tokenUsage()
                    ->whereDate('created_at', today())
                    ->selectRaw('
                        SUM(total_tokens) as today_tokens,
                        SUM(cost) as today_cost,
                        COUNT(*) as today_api_calls
                    ')
                    ->first();

                $thisMonthStats = $chatbot->tokenUsage()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->selectRaw('
                        SUM(total_tokens) as month_tokens,
                        SUM(cost) as month_cost,
                        COUNT(*) as month_api_calls
                    ')
                    ->first();

                return [
                    'id' => $chatbot->id,
                    'name' => $chatbot->name,
                    'description' => $chatbot->description,
                    'is_active' => $chatbot->is_active,
                    'created_at' => $chatbot->created_at->format('M d, Y'),

                    // Conversation stats
                    'total_conversations' => $chatbot->conversations_count,
                    'total_sources' => $chatbot->sources_count,

                    // Token usage stats
                    'total_tokens' => $tokenStats->total_tokens ?? 0,
                    'total_cost' => number_format($tokenStats->total_cost ?? 0, 6),
                    'total_api_calls' => $tokenStats->total_api_calls ?? 0,
                    'avg_tokens_per_call' => round($tokenStats->avg_tokens_per_call ?? 0, 2),

                    // Today stats
                    'today_tokens' => $todayStats->today_tokens ?? 0,
                    'today_cost' => number_format($todayStats->today_cost ?? 0, 6),
                    'today_api_calls' => $todayStats->today_api_calls ?? 0,

                    // This month stats
                    'month_tokens' => $thisMonthStats->month_tokens ?? 0,
                    'month_cost' => number_format($thisMonthStats->month_cost ?? 0, 4),
                    'month_api_calls' => $thisMonthStats->month_api_calls ?? 0,
                ];
            });

        // Overall platform stats
        $overallStats = [
            'total_chatbots' => $chatbots->count(),
            'active_chatbots' => $chatbots->where('is_active', true)->count(),
            'total_tokens_used' => $chatbots->sum(function($chatbot) {
                return is_numeric($chatbot['total_tokens']) ? $chatbot['total_tokens'] : 0;
            }),
            'total_cost' => $chatbots->sum(function($chatbot) {
                return floatval(str_replace(',', '', $chatbot['total_cost']));
            }),
            'total_conversations' => $chatbots->sum('total_conversations'),
            'today_tokens' => $chatbots->sum(function($chatbot) {
                return is_numeric($chatbot['today_tokens']) ? $chatbot['today_tokens'] : 0;
            }),
            'today_cost' => $chatbots->sum(function($chatbot) {
                return floatval(str_replace(',', '', $chatbot['today_cost']));
            }),
            'month_tokens' => $chatbots->sum(function($chatbot) {
                return is_numeric($chatbot['month_tokens']) ? $chatbot['month_tokens'] : 0;
            }),
            'month_cost' => $chatbots->sum(function($chatbot) {
                return floatval(str_replace(',', '', $chatbot['month_cost']));
            }),
        ];

        // Recent usage trends (last 7 days)
        $usageTrends = TokenUsage::selectRaw('
                DATE(created_at) as date,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost,
                COUNT(*) as api_calls
            ')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.chatbot-statistics.index', compact('chatbots', 'overallStats', 'usageTrends'));
    }

    /**
     * Show detailed statistics for a specific chatbot
     */
    public function show(Chatbot $chatbot)
    {
        $chatbot->load(['conversations', 'sources', 'tokenUsage']);

        // Daily usage for the last 30 days
        $dailyUsage = $chatbot->tokenUsage()
            ->selectRaw('
                DATE(created_at) as date,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost,
                COUNT(*) as api_calls,
                AVG(total_tokens) as avg_tokens
            ')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Model usage breakdown
        $modelUsage = $chatbot->tokenUsage()
            ->selectRaw('
                model,
                SUM(total_tokens) as tokens,
                SUM(cost) as cost,
                COUNT(*) as api_calls
            ')
            ->groupBy('model')
            ->orderBy('tokens', 'desc')
            ->get();

        // Recent conversations with token usage
        $recentConversations = $chatbot->conversations()
            ->with(['tokenUsage'])
            ->withCount('messages')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($conversation) {
                $tokenUsage = $conversation->tokenUsage->sum('total_tokens');
                $cost = $conversation->tokenUsage->sum('cost');

                return [
                    'id' => $conversation->id,
                    'created_at' => $conversation->created_at->format('M d, Y H:i'),
                    'messages_count' => $conversation->messages_count,
                    'total_tokens' => $tokenUsage,
                    'total_cost' => number_format($cost, 6),
                ];
            });

        return view('admin.chatbot-statistics.show', compact('chatbot', 'dailyUsage', 'modelUsage', 'recentConversations'));
    }
}
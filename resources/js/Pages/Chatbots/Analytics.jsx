import React, { useState, useEffect } from 'react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import Pagination from '../../Components/Pagination';
import { ChatBubbleLeftRightIcon, UserIcon, CalendarIcon, ClockIcon, ArrowPathIcon, ChartBarIcon, TagIcon } from '@heroicons/react/24/outline';
import { router } from '@inertiajs/react';

export default function ChatbotAnalytics({ chatbot, conversations, analytics, products }) {
    const [refreshing, setRefreshing] = useState(false);
    const [dateRange, setDateRange] = useState('7'); // days
    const [dynamicAnalytics, setDynamicAnalytics] = useState(analytics);

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const refreshAnalytics = async () => {
        setRefreshing(true);
        try {
            // Fetch updated analytics data
            const response = await fetch(`/chatbots/${chatbot.id}/analytics?date_range=${dateRange}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });
            if (response.ok) {
                const data = await response.json();
                setDynamicAnalytics(data.analytics);
            }
        } catch (error) {
            console.error('Failed to refresh analytics:', error);
        }
        setRefreshing(false);
    };

    const handleDateRangeChange = (range) => {
        setDateRange(range);
        router.visit(`/chatbots/${chatbot.id}/analytics?date_range=${range}`, {
            preserveState: true,
            only: ['analytics', 'conversations']
        });
    };

    // Auto-refresh every 30 seconds
    useEffect(() => {
        const interval = setInterval(refreshAnalytics, 30000);
        return () => clearInterval(interval);
    }, [dateRange]);

    // Calculate product analytics if available
    const productAnalytics = products ? {
        total_products: products.filter(p => p.type === 'product').length,
        total_services: products.filter(p => p.type === 'service').length,
        top_mentioned: products.sort((a, b) => (b.mention_count || 0) - (a.mention_count || 0)).slice(0, 3),
        avg_conversion: products.length > 0 ? (products.reduce((sum, p) => sum + (p.conversion_score || 0), 0) / products.length).toFixed(1) : 0
    } : null;

    const stats = [
        {
            name: 'Total Conversations',
            value: dynamicAnalytics.total_conversations || 0,
            icon: ChatBubbleLeftRightIcon,
            color: 'text-blue-600 bg-blue-100'
        },
        {
            name: 'This Week',
            value: dynamicAnalytics.conversations_this_week || 0,
            icon: CalendarIcon,
            color: 'text-green-600 bg-green-100'
        },
        {
            name: 'This Month',
            value: dynamicAnalytics.conversations_this_month || 0,
            icon: CalendarIcon,
            color: 'text-purple-600 bg-purple-100'
        },
        {
            name: 'Daily Average',
            value: Math.round(dynamicAnalytics.avg_daily_conversations || 0),
            icon: ClockIcon,
            color: 'text-orange-600 bg-orange-100'
        }
    ];

    // Add product stats if available
    if (productAnalytics) {
        stats.push({
            name: 'Products/Services',
            value: `${productAnalytics.total_products + productAnalytics.total_services}`,
            icon: TagIcon,
            color: 'text-indigo-600 bg-indigo-100'
        });

        if (productAnalytics.avg_conversion > 0) {
            stats.push({
                name: 'Avg Conversion',
                value: `${productAnalytics.avg_conversion}%`,
                icon: ChartBarIcon,
                color: 'text-teal-600 bg-teal-100'
            });
        }
    }

    return (
        <ChatbotLayout chatbot={chatbot} title={`Analytics - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                <div className="sm:flex sm:items-center">
                    <div className="sm:flex-auto">
                        <h1 className="text-base font-semibold leading-6 text-gray-900">
                            Analytics for {chatbot.name}
                        </h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Monitor your chatbot's performance and user interactions.
                            <span className="ml-2 text-xs text-green-600">
                                🔄 Auto-refreshing every 30s
                            </span>
                        </p>
                    </div>
                    <div className="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                        <div className="flex items-center space-x-4">
                            {/* Date Range Filter */}
                            <select
                                value={dateRange}
                                onChange={(e) => handleDateRangeChange(e.target.value)}
                                className="rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="1">Last 1 day</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 3 months</option>
                            </select>

                            {/* Refresh Button */}
                            <button
                                type="button"
                                onClick={refreshAnalytics}
                                disabled={refreshing}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                            >
                                <ArrowPathIcon className={`-ml-0.5 mr-1.5 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                                {refreshing ? 'Refreshing...' : 'Refresh'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <div key={stat.name} className="bg-white overflow-hidden shadow rounded-lg">
                                <div className="p-5">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <div className={`w-8 h-8 rounded-md flex items-center justify-center ${stat.color}`}>
                                                <Icon className="h-5 w-5" />
                                            </div>
                                        </div>
                                        <div className="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt className="text-sm font-medium text-gray-500 truncate">
                                                    {stat.name}
                                                </dt>
                                                <dd className="text-lg font-medium text-gray-900">
                                                    {stat.value}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Product Analytics */}
                {productAnalytics && productAnalytics.top_mentioned.length > 0 && (
                    <div className="mt-8">
                        <div className="sm:flex sm:items-center">
                            <div className="sm:flex-auto">
                                <h2 className="text-base font-semibold leading-6 text-gray-900">
                                    Product Performance
                                </h2>
                                <p className="mt-2 text-sm text-gray-700">
                                    Most mentioned programs and services in conversations.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 bg-white shadow overflow-hidden sm:rounded-md">
                            <ul className="divide-y divide-gray-200">
                                {productAnalytics.top_mentioned.map((product, index) => (
                                    <li key={product.id} className="px-6 py-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold ${
                                                        index === 0 ? 'bg-yellow-100 text-yellow-800' :
                                                        index === 1 ? 'bg-gray-100 text-gray-800' :
                                                        'bg-orange-100 text-orange-800'
                                                    }`}>
                                                        #{index + 1}
                                                    </div>
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {product.name}
                                                        <span className="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                                            {product.type}
                                                        </span>
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {product.mention_count || 0} mentions
                                                        {product.conversion_score && ` • ${product.conversion_score}% conversion`}
                                                    </div>
                                                </div>
                                            </div>
                                            {product.primary_url && (
                                                <div className="text-sm">
                                                    <a
                                                        href={product.primary_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        View →
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Recent Conversations */}
                <div className="mt-8">
                    <div className="sm:flex sm:items-center">
                        <div className="sm:flex-auto">
                            <h2 className="text-base font-semibold leading-6 text-gray-900">
                                Recent Conversations
                            </h2>
                            <p className="mt-2 text-sm text-gray-700">
                                Latest chat sessions with your chatbot.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 bg-white shadow overflow-hidden sm:rounded-md">
                        {conversations.data && conversations.data.length > 0 ? (
                            <ul className="divide-y divide-gray-200">
                                {conversations.data.map((conversation) => (
                                    <li key={conversation.id} className="px-6 py-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <UserIcon className="h-6 w-6 text-gray-400" />
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        Session #{conversation.session_id?.slice(-8) || 'Unknown'}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {conversation.messages_count || 0} messages
                                                        {conversation.ip_address && ` • IP: ${conversation.ip_address}`}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                {formatDate(conversation.created_at)}
                                            </div>
                                        </div>
                                        {conversation.last_message && (
                                            <div className="mt-2 ml-10">
                                                <p className="text-sm text-gray-600 truncate">
                                                    Last message: "{conversation.last_message}"
                                                </p>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="text-center py-12">
                                <ChatBubbleLeftRightIcon className="mx-auto h-12 w-12 text-gray-400" />
                                <h3 className="mt-2 text-sm font-semibold text-gray-900">No conversations yet</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Start testing your chatbot to see analytics data.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    <Pagination data={conversations} />
                </div>

                {/* Usage Tips */}
                <div className="mt-8 bg-blue-50 border border-blue-200 rounded-md p-4">
                    <h3 className="text-sm font-medium text-blue-900">Analytics Tips</h3>
                    <div className="mt-2 text-sm text-blue-700">
                        <ul className="list-disc list-inside space-y-1">
                            <li>Monitor conversation trends to understand user behavior</li>
                            <li>Check daily averages to identify peak usage times</li>
                            <li>Review recent conversations to improve your knowledge base</li>
                            <li>Track growth over time to measure chatbot adoption</li>
                        </ul>
                    </div>
                </div>
            </div>
        </ChatbotLayout>
    );
}

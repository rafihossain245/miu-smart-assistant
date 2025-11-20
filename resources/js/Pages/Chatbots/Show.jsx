import React from 'react';
import { Link } from '@inertiajs/react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import {
    DocumentTextIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
    CodeBracketIcon,
    ChartBarIcon,
    EyeIcon,
    CheckCircleIcon
} from '@heroicons/react/24/outline';

export default function ShowChatbot({ chatbot, stats }) {
    return (
        <ChatbotLayout chatbot={chatbot} title={chatbot.name}>
            <div className="flex-1 p-6 overflow-auto">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex justify-between items-center">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                            <p className="text-gray-600 mt-1">Overview of your chatbot performance and quick actions</p>
                        </div>
                        <div className="flex space-x-3">
                            <Link
                                href={`/chatbots/${chatbot.id}/test`}
                                className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            >
                                <EyeIcon className="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400" />
                                Test
                            </Link>
                            <Link
                                href={`/chatbots/${chatbot.id}/edit`}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                <Cog6ToothIcon className="-ml-0.5 mr-1.5 h-5 w-5" />
                                Settings
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <DocumentTextIcon className="h-6 w-6 text-gray-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Total Sources
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.total_sources}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <DocumentTextIcon className="h-6 w-6 text-green-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Processed Sources
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.completed_sources}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <ChatBubbleLeftRightIcon className="h-6 w-6 text-blue-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Total Conversations
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.total_conversations}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <ChatBubbleLeftRightIcon className="h-6 w-6 text-indigo-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Today's Conversations
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.conversations_today}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recent Sources */}
                {chatbot.sources && chatbot.sources.length > 0 && (
                    <div className="mt-8">
                        <div className="flex justify-between items-center">
                            <h3 className="text-lg leading-6 font-medium text-gray-900">Recent Sources</h3>
                            <Link
                                href={`/chatbots/${chatbot.id}/sources`}
                                className="text-sm text-indigo-600 hover:text-indigo-500"
                            >
                                View all sources
                            </Link>
                        </div>
                        <div className="mt-4 bg-white shadow overflow-hidden sm:rounded-md">
                            <ul className="divide-y divide-gray-200">
                                {chatbot.sources.slice(0, 5).map((source) => (
                                    <li key={source.id}>
                                        <div className="px-4 py-4 flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <DocumentTextIcon className="h-5 w-5 text-gray-400" />
                                                </div>
                                                <div className="ml-4">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {source.title}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {source.type.charAt(0).toUpperCase() + source.type.slice(1)}
                                                        {source.url && ` • ${source.url.substring(0, 50)}...`}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                    source.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                    source.status === 'processing' ? 'bg-blue-100 text-blue-800' :
                                                    source.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                    'bg-yellow-100 text-yellow-800'
                                                }`}>
                                                    {source.status.charAt(0).toUpperCase() + source.status.slice(1)}
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
            </div>
        </ChatbotLayout>
    );
}
import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { PlusIcon, ChatBubbleLeftRightIcon, DocumentTextIcon, CursorArrowRaysIcon } from '@heroicons/react/24/outline';

export default function Dashboard({ stats, recentChatbots }) {
    return (
        <AppLayout title="Dashboard">
            <div className="px-4 py-6 sm:px-0">
                {/* Stats Grid */}
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <ChatBubbleLeftRightIcon className="h-6 w-6 text-gray-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Total Chatbots
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.total_chatbots}
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
                                    <CursorArrowRaysIcon className="h-6 w-6 text-green-400" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            Active Chatbots
                                        </dt>
                                        <dd className="text-lg font-medium text-gray-900">
                                            {stats.active_chatbots}
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
                                    <DocumentTextIcon className="h-6 w-6 text-blue-400" />
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
                                    <DocumentTextIcon className="h-6 w-6 text-indigo-400" />
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

                {/* Quick Actions */}
                <div className="mt-8">
                    <div className="flex justify-between items-center">
                        <h3 className="text-lg leading-6 font-medium text-gray-900">Quick Actions</h3>
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            href="/chatbots/create"
                            className="relative block w-full rounded-lg border-2 border-dashed border-gray-300 p-12 text-center hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <PlusIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <span className="mt-2 block text-sm font-medium text-gray-900">
                                Create New Chatbot
                            </span>
                        </Link>
                    </div>
                </div>

                {/* Recent Chatbots */}
                {recentChatbots.length > 0 && (
                    <div className="mt-8">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Chatbots</h3>
                        <div className="bg-white shadow overflow-hidden sm:rounded-md">
                            <ul className="divide-y divide-gray-200">
                                {recentChatbots.map((chatbot) => (
                                    <li key={chatbot.id}>
                                        <Link
                                            href={`/chatbots/${chatbot.id}`}
                                            className="block hover:bg-gray-50 px-4 py-4 sm:px-6"
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <div className="flex-shrink-0">
                                                        <ChatBubbleLeftRightIcon className="h-6 w-6 text-gray-400" />
                                                    </div>
                                                    <div className="ml-4">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {chatbot.name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {chatbot.sources_count} sources • {chatbot.conversations_count} conversations
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center">
                                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                        chatbot.is_active
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {chatbot.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </div>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppLayout from './AppLayout';
import {
    DocumentTextIcon,
    EyeIcon,
    CodeBracketIcon,
    ChartBarIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
    ShoppingBagIcon,
    AcademicCapIcon
} from '@heroicons/react/24/outline';

const sidebarItems = [
    {
        name: 'Manage Sources',
        description: 'Add & manage knowledge base',
        href: 'chatbots.sources',
        icon: DocumentTextIcon,
    },
    {
        name: 'Products & Services',
        description: 'Manage your offerings',
        href: 'chatbots.products',
        icon: ShoppingBagIcon,
    },
    {
        name: 'Training Data',
        description: 'Query examples & AI training',
        href: 'chatbots.training',
        icon: AcademicCapIcon,
    },
    {
        name: 'Test Chatbot',
        description: 'Test responses in sandbox',
        href: 'chatbots.test',
        icon: EyeIcon,
    },
    {
        name: 'Get Embed Code',
        description: 'Embed on your website',
        href: 'chatbots.embed',
        icon: CodeBracketIcon,
    },
    {
        name: 'View Analytics',
        description: 'Conversation insights',
        href: 'chatbots.analytics',
        icon: ChartBarIcon,
    },
    {
        name: 'View Conversations',
        description: 'Chat history management',
        href: 'chatbots.conversations',
        icon: ChatBubbleLeftRightIcon,
    },
    {
        name: 'Settings',
        description: 'Chatbot configuration',
        href: 'chatbots.edit',
        icon: Cog6ToothIcon,
    },
];

export default function ChatbotLayout({ chatbot, children, title, enableScroll = true }) {
    const { url } = usePage();

    const isActive = (routeName) => {
        const routePaths = {
            'chatbots.sources': `/chatbots/${chatbot.id}/sources`,
            'chatbots.products': `/chatbots/${chatbot.id}/products`,
            'chatbots.training': `/chatbots/${chatbot.id}/training`,
            'chatbots.test': `/chatbots/${chatbot.id}/test`,
            'chatbots.embed': `/chatbots/${chatbot.id}/embed`,
            'chatbots.analytics': `/chatbots/${chatbot.id}/analytics`,
            'chatbots.conversations': `/chatbots/${chatbot.id}/conversations`,
            'chatbots.edit': `/chatbots/${chatbot.id}/edit`,
        };
        return url.includes(routePaths[routeName]);
    };

    return (
        <AppLayout title={title}>
            <div className="flex h-screen bg-gray-50 gap-6">
                {/* Sidebar */}
                <div className="w-80 bg-white shadow-sm border-r border-gray-200 flex flex-col">
                    {/* Chatbot Header */}
                    <div className="p-6 border-b border-gray-200">
                        <Link
                            href={`/chatbots/${chatbot.id}`}
                            className="block group"
                        >
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                                    {chatbot.name}
                                </h2>
                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                    chatbot.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-800'
                                }`}>
                                    {chatbot.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 mt-1">
                                {chatbot.description || 'No description provided'}
                            </p>
                        </Link>
                    </div>

                    {/* Quick Actions */}
                    <div className="p-6 flex-1 flex flex-col overflow-hidden">
                        <h3 className="text-sm font-medium text-gray-900 mb-4">Quick Actions</h3>
                        <nav className="space-y-2 overflow-y-auto flex-1 pr-2" style={{
                            scrollbarWidth: 'thin',
                            scrollbarColor: '#D1D5DB #F3F4F6'
                        }}>
                            {sidebarItems.map((item) => {
                                const Icon = item.icon;
                                const active = isActive(item.href);

                                return (
                                    <Link
                                        key={item.name}
                                        href={`/chatbots/${chatbot.id}/${item.href.split('.')[1]}`}
                                        className={`group flex items-start p-3 rounded-lg transition-colors ${
                                            active
                                                ? 'bg-indigo-50 border border-indigo-200'
                                                : 'hover:bg-gray-50 border border-transparent'
                                        }`}
                                    >
                                        <Icon className={`h-6 w-6 flex-shrink-0 ${
                                            active ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600'
                                        }`} />
                                        <div className="ml-3">
                                            <p className={`text-sm font-medium ${
                                                active ? 'text-indigo-900' : 'text-gray-900'
                                            }`}>
                                                {item.name}
                                            </p>
                                            <p className={`text-xs mt-1 ${
                                                active ? 'text-indigo-700' : 'text-gray-500'
                                            }`}>
                                                {item.description}
                                            </p>
                                        </div>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                </div>

                {/* Main Content */}
                <div className={`flex-1 flex flex-col ${enableScroll ? 'overflow-auto' : 'overflow-hidden'}`}>
                    {children}
                </div>
            </div>
        </AppLayout>
    );
}
import React, { useState } from 'react';
import { useForm, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { SwatchIcon, ChatBubbleLeftRightIcon, ArrowLeftIcon, PhoneIcon } from '@heroicons/react/24/outline';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

export default function EditChatbot({ chatbot, flash }) {
    const [activeTab, setActiveTab] = useState('general');

    const { data, setData, put, processing, errors, reset } = useForm({
        name: chatbot.name || '',
        description: chatbot.description || '',
        welcome_message: chatbot.welcome_message || '',
        initial_message: chatbot.initial_message || "Hello! I'm your AI assistant for this knowledge base. I'm here to help answer questions based on the information provided. How can I assist you today?",
        is_active: chatbot.is_active || false,

        // Advanced Settings
        customer_personas: chatbot.customer_personas || [],
        brand_voice: chatbot.brand_voice || 'professional_friendly',
        integrations: chatbot.integrations || [],
        service_urls: chatbot.service_urls || [],
        contact_settings: chatbot.contact_settings || {
            whatsapp_number: '',
            phone_number: '',
            email_address: '',
            support_email: '',
            support_url: '',
            business_hours: '',
            support_message: '',
            enabled: false
        },
        appearance: {
            primary_color: chatbot.appearance?.primary_color || '#4F46E5',
            secondary_color: chatbot.appearance?.secondary_color || '#E5E7EB',
            text_color: chatbot.appearance?.text_color || '#111827',
            background_color: chatbot.appearance?.background_color || '#FFFFFF',
            border_radius: chatbot.appearance?.border_radius || '12',
            font_family: chatbot.appearance?.font_family || 'Inter',
            position: chatbot.appearance?.position || 'bottom-right',
            icon_style: chatbot.appearance?.icon_style || 'modern',
            chat_bubble_style: chatbot.appearance?.chat_bubble_style || 'rounded',
            header_text: chatbot.appearance?.header_text || chatbot.name,
            header_subtitle: chatbot.appearance?.header_subtitle || 'AI Assistant',
            show_typing_indicator: chatbot.appearance?.show_typing_indicator ?? true,
            show_timestamps: chatbot.appearance?.show_timestamps ?? false,
            enable_file_upload: chatbot.appearance?.enable_file_upload ?? false,
            icon_type: chatbot.appearance?.icon_type || 'emoji',
            custom_icon: chatbot.appearance?.custom_icon || null,
            selected_emoji: chatbot.appearance?.selected_emoji || '🤖',
            selected_svg: chatbot.appearance?.selected_svg || 'chat',
        }
    });

    const submit = (e) => {
        e.preventDefault();

        put(`/chatbots/${chatbot.id}`, {
            data,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success('Assistant settings saved successfully!');
            },
            onError: (errors) => {
                toast.error('Failed to save settings. Please check for errors and try again.');
                console.error('Validation errors:', errors);
            }
        });
    };

    const tabs = [
        { id: 'general', name: 'General', icon: ChatBubbleLeftRightIcon },
        { id: 'contact', name: 'Contact', icon: PhoneIcon },
        { id: 'appearance', name: 'Appearance', icon: SwatchIcon },
    ];

    const fontOptions = [
        { value: 'Inter', label: 'Inter' },
        { value: 'Arial', label: 'Arial' },
        { value: 'Helvetica', label: 'Helvetica' },
        { value: 'Georgia', label: 'Georgia' },
        { value: 'Times New Roman', label: 'Times New Roman' },
    ];

    const positionOptions = [
        { value: 'bottom-right', label: 'Bottom Right' },
        { value: 'bottom-left', label: 'Bottom Left' },
        { value: 'top-right', label: 'Top Right' },
        { value: 'top-left', label: 'Top Left' },
    ];

    // Function to render bot icon consistently
    const renderBotIcon = (size = 'sm') => {
        const sizeClasses = {
            xs: 'w-4 h-4',
            sm: 'w-5 h-5',
            md: 'w-6 h-6',
            lg: 'w-8 h-8'
        };

        const textSizes = {
            xs: 'text-xs',
            sm: 'text-sm',
            md: 'text-base',
            lg: 'text-lg'
        };

        if (data.appearance.icon_type === 'emoji' && data.appearance.selected_emoji) {
            return <span className={`text-white ${textSizes[size]}`}>{data.appearance.selected_emoji}</span>;
        } else if (data.appearance.icon_type === 'svg' && data.appearance.selected_svg) {
            const iconName = data.appearance.selected_svg;
            const svgIcons = {
                chat: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>,
                support: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg>,
                robot: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>,
                assistant: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
                help: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>,
                message: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
            };
            return svgIcons[iconName] || svgIcons.chat;
        } else if (data.appearance.icon_type === 'upload' && data.appearance.custom_icon) {
            return (
                <img
                    src={data.appearance.custom_icon}
                    alt="Bot icon"
                    className={`${sizeClasses[size]} rounded-full object-cover`}
                />
            );
        } else {
            // Default SVG chat icon
            return (
                <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" />
                </svg>
            );
        }
    };

    return (
        <AppLayout title={`Assistant Settings - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                {/* Back Button */}
                <div className="mb-4">
                    <Link
                        href={`/chatbots/${chatbot.id}`}
                        className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                        <ArrowLeftIcon className="-ml-1 mr-1 h-5 w-5" />
                        Back to {chatbot.name}
                    </Link>
                </div>

                <div className="md:flex md:items-center md:justify-between">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                            Assistant Settings
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage the core MIU Smart Assistant settings and contact details.
                        </p>
                    </div>
                    <div className="mt-4 flex md:ml-4 md:mt-0 space-x-3">
                        <Link
                            href={`/chatbots/${chatbot.id}`}
                            className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                    </div>
                </div>

                {/* Success Message */}
                {flash?.success && (
                    <div className="mt-4 rounded-md bg-green-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-green-800">
                                    {flash.success}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tabs */}
                <div className="mt-8">
                    <div className="border-b border-gray-200">
                        <nav className="-mb-px flex space-x-8">
                            {tabs.map((tab) => {
                                const Icon = tab.icon;
                                return (
                                    <button
                                        key={tab.id}
                                        onClick={() => setActiveTab(tab.id)}
                                        className={`flex items-center py-2 px-1 border-b-2 font-medium text-sm ${
                                            activeTab === tab.id
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}
                                    >
                                        <Icon className="mr-2 h-5 w-5" />
                                        {tab.name}
                                    </button>
                                );
                            })}
                        </nav>
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Left Side - Form */}
                    <div>
                        <form onSubmit={submit}>
                            {activeTab === 'general' && (
                        <div className="bg-white shadow sm:rounded-lg">
                            <div className="px-4 py-5 sm:p-6 space-y-6">
                                <div>
                                    <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                        Assistant Name
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Enter assistant name"
                                    />
                                    {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                <div>
                                    <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                                        Description
                                    </label>
                                    <textarea
                                        id="description"
                                        rows={3}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Describe what the MIU assistant helps with"
                                    />
                                    {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                </div>

                                <div>
                                    <label htmlFor="welcome_message" className="block text-sm font-medium text-gray-700">
                                        Welcome Message
                                    </label>
                                    <textarea
                                        id="welcome_message"
                                        rows={3}
                                        value={data.welcome_message}
                                        onChange={(e) => setData('welcome_message', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Enter the welcome message users will see"
                                    />
                                    {errors.welcome_message && <p className="mt-2 text-sm text-red-600">{errors.welcome_message}</p>}
                                </div>

                                <div>
                                    <label htmlFor="initial_message" className="block text-sm font-medium text-gray-700">
                                        Initial Chat Message
                                    </label>
                                    <textarea
                                        id="initial_message"
                                        rows={3}
                                        value={data.initial_message}
                                        onChange={(e) => setData('initial_message', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Enter the initial message shown when users start chatting"
                                    />
                                    <p className="mt-1 text-sm text-gray-500">This message will be shown as the first message when users start chatting with the assistant.</p>
                                    {errors.initial_message && <p className="mt-2 text-sm text-red-600">{errors.initial_message}</p>}
                                </div>

                                <div>
                                    <div className="flex items-center">
                                        <input
                                            id="is_active"
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                            Active (users can interact with this assistant)
                                        </label>
                                    </div>
                                    {errors.is_active && <p className="mt-2 text-sm text-red-600">{errors.is_active}</p>}
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'appearance' && (
                        <div className="space-y-8">
                            {/* Colors Section */}
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Colors</h3>
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Primary Color</label>
                                            <div className="mt-1 flex items-center space-x-3">
                                                <input
                                                    type="color"
                                                    value={data.appearance.primary_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, primary_color: e.target.value })}
                                                    className="h-10 w-20 rounded border border-gray-300"
                                                />
                                                <input
                                                    type="text"
                                                    value={data.appearance.primary_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, primary_color: e.target.value })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Background Color</label>
                                            <div className="mt-1 flex items-center space-x-3">
                                                <input
                                                    type="color"
                                                    value={data.appearance.background_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, background_color: e.target.value })}
                                                    className="h-10 w-20 rounded border border-gray-300"
                                                />
                                                <input
                                                    type="text"
                                                    value={data.appearance.background_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, background_color: e.target.value })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Text Color</label>
                                            <div className="mt-1 flex items-center space-x-3">
                                                <input
                                                    type="color"
                                                    value={data.appearance.text_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, text_color: e.target.value })}
                                                    className="h-10 w-20 rounded border border-gray-300"
                                                />
                                                <input
                                                    type="text"
                                                    value={data.appearance.text_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, text_color: e.target.value })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Secondary Color</label>
                                            <div className="mt-1 flex items-center space-x-3">
                                                <input
                                                    type="color"
                                                    value={data.appearance.secondary_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, secondary_color: e.target.value })}
                                                    className="h-10 w-20 rounded border border-gray-300"
                                                />
                                                <input
                                                    type="text"
                                                    value={data.appearance.secondary_color}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, secondary_color: e.target.value })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Typography & Layout */}
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Typography & Layout</h3>
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Font Family</label>
                                            <select
                                                value={data.appearance.font_family}
                                                onChange={(e) => setData('appearance', { ...data.appearance, font_family: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            >
                                                {fontOptions.map((font) => (
                                                    <option key={font.value} value={font.value}>{font.label}</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Position</label>
                                            <select
                                                value={data.appearance.position}
                                                onChange={(e) => setData('appearance', { ...data.appearance, position: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            >
                                                {positionOptions.map((position) => (
                                                    <option key={position.value} value={position.value}>{position.label}</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Border Radius (px)</label>
                                            <input
                                                type="number"
                                                min="0"
                                                max="50"
                                                value={data.appearance.border_radius}
                                                onChange={(e) => setData('appearance', { ...data.appearance, border_radius: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Header Settings */}
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Header Settings</h3>
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Header Text</label>
                                            <input
                                                type="text"
                                                value={data.appearance.header_text}
                                                onChange={(e) => setData('appearance', { ...data.appearance, header_text: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="e.g., Customer Support"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Header Subtitle</label>
                                            <input
                                                type="text"
                                                value={data.appearance.header_subtitle}
                                                onChange={(e) => setData('appearance', { ...data.appearance, header_subtitle: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="e.g., AI Assistant"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Bot Icon */}
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Bot Icon</h3>

                                    {/* Icon Type Selection */}
                                    <div className="mb-6">
                                        <label className="block text-sm font-medium text-gray-700 mb-3">Icon Type</label>
                                        <div className="flex space-x-4">
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="icon_type"
                                                    value="emoji"
                                                    checked={data.appearance.icon_type === 'emoji'}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, icon_type: e.target.value })}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-900">Emoji</span>
                                            </label>
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="icon_type"
                                                    value="svg"
                                                    checked={data.appearance.icon_type === 'svg'}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, icon_type: e.target.value })}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-900">SVG Icons</span>
                                            </label>
                                            <label className="flex items-center">
                                                <input
                                                    type="radio"
                                                    name="icon_type"
                                                    value="upload"
                                                    checked={data.appearance.icon_type === 'upload'}
                                                    onChange={(e) => setData('appearance', { ...data.appearance, icon_type: e.target.value })}
                                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
                                                <span className="ml-2 text-sm text-gray-900">Upload Custom</span>
                                            </label>
                                        </div>
                                    </div>

                                    {/* Emoji Selection */}
                                    {data.appearance.icon_type === 'emoji' && (
                                        <div className="mb-6">
                                            <label className="block text-sm font-medium text-gray-700 mb-3">Choose Emoji</label>
                                            <div className="grid grid-cols-8 gap-2">
                                                {['🤖', '👨‍💼', '👩‍💼', '🧑‍💻', '👨‍🔧', '👩‍🔧', '🎯', '💼', '📞', '💬', '📧', '🌟', '⭐', '✨', '🚀', '💡'].map((emoji) => (
                                                    <button
                                                        key={emoji}
                                                        type="button"
                                                        onClick={() => setData('appearance', { ...data.appearance, selected_emoji: emoji })}
                                                        className={`p-3 text-2xl rounded-lg border-2 transition-all hover:scale-110 ${
                                                            data.appearance.selected_emoji === emoji
                                                                ? 'border-indigo-500 bg-indigo-50'
                                                                : 'border-gray-200 hover:border-gray-300'
                                                        }`}
                                                    >
                                                        {emoji}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* SVG Icon Selection */}
                                    {data.appearance.icon_type === 'svg' && (
                                        <div className="mb-6">
                                            <label className="block text-sm font-medium text-gray-700 mb-3">Choose SVG Icon</label>
                                            <div className="grid grid-cols-6 gap-3">
                                                {[
                                                    { name: 'chat', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg> },
                                                    { name: 'support', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg> },
                                                    { name: 'robot', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg> },
                                                    { name: 'assistant', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> },
                                                    { name: 'help', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg> },
                                                    { name: 'message', svg: <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg> }
                                                ].map((icon) => (
                                                    <button
                                                        key={icon.name}
                                                        type="button"
                                                        onClick={() => setData('appearance', { ...data.appearance, selected_svg: icon.name })}
                                                        className={`p-4 rounded-lg border-2 transition-all hover:scale-105 flex items-center justify-center ${
                                                            data.appearance.selected_svg === icon.name
                                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
                                                                : 'border-gray-200 hover:border-gray-300 text-gray-600 hover:text-gray-800'
                                                        }`}
                                                        title={icon.name}
                                                    >
                                                        {icon.svg}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Custom Icon Upload */}
                                    {data.appearance.icon_type === 'upload' && (
                                        <div className="mb-6">
                                            <label className="block text-sm font-medium text-gray-700 mb-3">Upload Custom Icon</label>
                                            <div className="flex items-center space-x-4">
                                                <div className="flex-1">
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={(e) => {
                                                            const file = e.target.files[0];
                                                            if (file) {
                                                                const reader = new FileReader();
                                                                reader.onload = (event) => {
                                                                    setData('appearance', { ...data.appearance, custom_icon: event.target.result });
                                                                };
                                                                reader.readAsDataURL(file);
                                                            }
                                                        }}
                                                        className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                                    />
                                                </div>
                                                {data.appearance.custom_icon && (
                                                    <div className="flex-shrink-0">
                                                        <img
                                                            src={data.appearance.custom_icon}
                                                            alt="Custom icon preview"
                                                            className="w-12 h-12 rounded-full object-cover border-2 border-gray-200"
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                            <p className="mt-2 text-sm text-gray-500">
                                                Recommended: Square image, 64x64px or larger. Supports PNG, JPG, SVG.
                                            </p>
                                        </div>
                                    )}

                                    {/* Icon Preview */}
                                    <div className="bg-gray-50 rounded-lg p-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                                        <div className="flex items-center space-x-3">
                                            <div
                                                className="w-12 h-12 rounded-full flex items-center justify-center"
                                                style={{ backgroundColor: data.appearance.primary_color }}
                                            >
                                                {data.appearance.icon_type === 'emoji' ? (
                                                    <span className="text-xl">{data.appearance.selected_emoji}</span>
                                                ) : data.appearance.icon_type === 'svg' ? (
                                                    <div className="text-white">
                                                        {data.appearance.selected_svg === 'chat' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'support' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'robot' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>}
                                                        {data.appearance.selected_svg === 'assistant' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                                                        {data.appearance.selected_svg === 'help' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'message' && <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>}
                                                    </div>
                                                ) : data.appearance.custom_icon ? (
                                                    <img
                                                        src={data.appearance.custom_icon}
                                                        alt="Custom icon"
                                                        className="w-8 h-8 rounded-full object-cover"
                                                    />
                                                ) : (
                                                    <span className="text-white text-xs">No Icon</span>
                                                )}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{data.appearance.header_text}</p>
                                                <p className="text-xs text-gray-500">{data.appearance.header_subtitle}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Features */}
                            <div className="bg-white shadow sm:rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">Features</h3>
                                    <div className="space-y-4">
                                        <div className="flex items-center">
                                            <input
                                                id="show_typing_indicator"
                                                type="checkbox"
                                                checked={data.appearance.show_typing_indicator}
                                                onChange={(e) => setData('appearance', { ...data.appearance, show_typing_indicator: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="show_typing_indicator" className="ml-2 block text-sm text-gray-900">
                                                Show typing indicator
                                            </label>
                                        </div>

                                        <div className="flex items-center">
                                            <input
                                                id="show_timestamps"
                                                type="checkbox"
                                                checked={data.appearance.show_timestamps}
                                                onChange={(e) => setData('appearance', { ...data.appearance, show_timestamps: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="show_timestamps" className="ml-2 block text-sm text-gray-900">
                                                Show message timestamps
                                            </label>
                                        </div>

                                        <div className="flex items-center">
                                            <input
                                                id="enable_file_upload"
                                                type="checkbox"
                                                checked={data.appearance.enable_file_upload}
                                                onChange={(e) => setData('appearance', { ...data.appearance, enable_file_upload: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <label htmlFor="enable_file_upload" className="ml-2 block text-sm text-gray-900">
                                                Enable file uploads (coming soon)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'contact' && (
                        <div className="bg-white shadow sm:rounded-lg">
                            <div className="px-4 py-5 sm:p-6 space-y-6">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">Contact Settings</h3>
                                    <p className="text-sm text-gray-500 mb-4">
                                        Configure MIU contact information that will be shared when users ask to communicate with your team.
                                    </p>

                                    {/* Enable Contact Settings */}
                                    <div className="mb-6">
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={data.contact_settings.enabled}
                                                onChange={(e) => setData('contact_settings', {
                                                    ...data.contact_settings,
                                                    enabled: e.target.checked
                                                })}
                                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span className="ml-2 text-sm font-medium text-gray-700">
                                                Enable contact information sharing
                                            </span>
                                        </label>
                                        <p className="mt-1 text-xs text-gray-500">
                                            When enabled, the assistant will provide this contact information when users ask to communicate with MIU.
                                        </p>
                                    </div>

                                    {data.contact_settings.enabled && (
                                        <div className="space-y-4">
                                            {/* WhatsApp Number */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    WhatsApp Number
                                                </label>
                                                <input
                                                    type="text"
                                                    placeholder="+1234567890"
                                                    value={data.contact_settings.whatsapp_number}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        whatsapp_number: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">Include country code (e.g., +1234567890)</p>
                                            </div>

                                            {/* Phone Number */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Phone Number
                                                </label>
                                                <input
                                                    type="text"
                                                    placeholder="+1 (555) 123-4567"
                                                    value={data.contact_settings.phone_number}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        phone_number: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                            </div>

                                            {/* Email Address */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Email Address
                                                </label>
                                                <input
                                                    type="email"
                                                    placeholder="contact@company.com"
                                                    value={data.contact_settings.email_address}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        email_address: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">General contact email address</p>
                                            </div>

                                            {/* Support Email */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Support Email
                                                </label>
                                                <input
                                                    type="email"
                                                    placeholder="support@company.com"
                                                    value={data.contact_settings.support_email}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        support_email: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">Dedicated support email for customer inquiries</p>
                                            </div>

                                            {/* Support URL */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Support URL
                                                </label>
                                                <input
                                                    type="url"
                                                    placeholder="https://company.com/support"
                                                    value={data.contact_settings.support_url}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        support_url: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">Link to your help center or support portal</p>
                                            </div>

                                            {/* Business Hours */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Business Hours
                                                </label>
                                                <input
                                                    type="text"
                                                    placeholder="Monday - Friday, 9:00 AM - 6:00 PM EST"
                                                    value={data.contact_settings.business_hours}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        business_hours: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                            </div>

                                            {/* Support Message */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Custom Support Message
                                                </label>
                                                <textarea
                                                    rows="3"
                                                    placeholder="Feel free to contact our support team through any of the channels below for immediate assistance."
                                                    value={data.contact_settings.support_message}
                                                    onChange={(e) => setData('contact_settings', {
                                                        ...data.contact_settings,
                                                        support_message: e.target.value
                                                    })}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <p className="mt-1 text-xs text-gray-500">This message will be shown when users request contact information.</p>
                                            </div>

                                            {/* Contact Preview */}
                                            <div className="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                                <h4 className="text-sm font-medium text-gray-900 mb-3">Contact Information Preview</h4>
                                                <div className="text-sm text-gray-600 space-y-2">
                                                    <p>This is how your contact information will appear to users:</p>
                                                    <div className="bg-white p-3 rounded border border-gray-200 text-sm">
                                                        {data.contact_settings.support_message && (
                                                            <p className="mb-3 text-gray-700">{data.contact_settings.support_message}</p>
                                                        )}
                                                        <div className="space-y-1">
                                                            {data.contact_settings.whatsapp_number && (
                                                                <div>📱 <strong>WhatsApp:</strong> {data.contact_settings.whatsapp_number}</div>
                                                            )}
                                                            {data.contact_settings.phone_number && (
                                                                <div>📞 <strong>Phone:</strong> {data.contact_settings.phone_number}</div>
                                                            )}
                                                            {data.contact_settings.email_address && (
                                                                <div>✉️ <strong>Email:</strong> {data.contact_settings.email_address}</div>
                                                            )}
                                                            {data.contact_settings.support_email && (
                                                                <div>🛠️ <strong>Support Email:</strong> {data.contact_settings.support_email}</div>
                                                            )}
                                                            {data.contact_settings.support_url && (
                                                                <div>🌐 <strong>Support Portal:</strong> <a href={data.contact_settings.support_url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">{data.contact_settings.support_url}</a></div>
                                                            )}
                                                            {data.contact_settings.business_hours && (
                                                                <div>🕒 <strong>Business Hours:</strong> {data.contact_settings.business_hours}</div>
                                                            )}
                                                        </div>
                                                        {!data.contact_settings.whatsapp_number && !data.contact_settings.phone_number && !data.contact_settings.email_address && !data.contact_settings.support_email && !data.contact_settings.support_url && (
                                                            <p className="text-gray-500 italic">No contact information provided yet.</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                            {/* Submit Button */}
                            <div className="mt-8 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Saving...
                                        </>
                                    ) : (
                                        'Save Changes'
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Right Side - Live Preview */}
                    <div className="lg:sticky lg:top-8">
                        <div className="bg-white shadow sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-6">Live Preview</h3>

                            {/* Preview Container */}
                            <div className="bg-gray-100 rounded-lg p-4 min-h-[500px] relative">
                                {/* Chat Widget Preview */}
                                <div className="absolute bottom-4 right-4">
                                    <div
                                        className="w-14 h-14 rounded-full flex items-center justify-center text-white shadow-lg cursor-pointer hover:scale-110 transition-transform"
                                        style={{
                                            backgroundColor: data.appearance.primary_color,
                                            borderRadius: `${data.appearance.border_radius}px`
                                        }}
                                    >
                                        <ChatBubbleLeftRightIcon className="w-7 h-7" />
                                    </div>
                                </div>

                                {/* Chat Interface Preview */}
                                <div
                                    className="bg-white rounded-lg shadow-xl w-80 h-96 mx-auto flex flex-col overflow-hidden"
                                    style={{
                                        borderRadius: `${data.appearance.border_radius}px`
                                    }}
                                >
                                    {/* Header */}
                                    <div
                                        className="p-4 text-white"
                                        style={{ backgroundColor: data.appearance.primary_color }}
                                    >
                                        <div className="flex items-center space-x-3">
                                            <div className="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                                {renderBotIcon('sm')}
                                            </div>
                                            <div>
                                                <h4 className="font-medium text-sm">{data.appearance.header_text}</h4>
                                                <p className="text-xs opacity-90">{data.appearance.header_subtitle}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Messages Preview */}
                                    <div className="flex-1 p-4 space-y-3 overflow-y-auto">
                                        {/* Bot Message */}
                                        <div className="flex items-start space-x-2">
                                            <div
                                                className="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                                                style={{ backgroundColor: data.appearance.primary_color }}
                                            >
                                                {renderBotIcon('xs')}
                                                    <div className="text-white">
                                                        {data.appearance.selected_svg === 'chat' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'support' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'robot' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>}
                                                        {data.appearance.selected_svg === 'assistant' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                                                        {data.appearance.selected_svg === 'help' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'message' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>}
                                                        {!data.appearance.selected_svg && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>}
                                                    </div>
                                            </div>
                                            <div
                                                className="rounded-lg px-3 py-2 text-sm max-w-xs"
                                                style={{
                                                    backgroundColor: data.appearance.secondary_color,
                                                    color: data.appearance.text_color
                                                }}
                                            >
                                                {data.initial_message || "Hello! I'm your AI assistant for this knowledge base. I'm here to help answer questions based on the information provided. How can I assist you today?"}
                                            </div>
                                        </div>

                                        {/* User Message */}
                                        <div className="flex items-start space-x-2 justify-end">
                                            <div
                                                className="rounded-lg px-3 py-2 text-sm text-white"
                                                style={{ backgroundColor: data.appearance.primary_color }}
                                            >
                                                Can you help me?
                                            </div>
                                            <div className="w-6 h-6 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                <span className="text-white text-xs">👤</span>
                                            </div>
                                        </div>

                                        {/* Bot Response */}
                                        <div className="flex items-start space-x-2">
                                            <div
                                                className="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                                                style={{ backgroundColor: data.appearance.primary_color }}
                                            >
                                                {renderBotIcon('xs')}
                                                    <div className="text-white">
                                                        {data.appearance.selected_svg === 'chat' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'support' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'robot' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>}
                                                        {data.appearance.selected_svg === 'assistant' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                                                        {data.appearance.selected_svg === 'help' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>}
                                                        {data.appearance.selected_svg === 'message' && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>}
                                                        {!data.appearance.selected_svg && <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>}
                                                    </div>
                                            </div>
                                            <div
                                                className="rounded-lg px-3 py-2 text-sm max-w-xs"
                                                style={{
                                                    backgroundColor: data.appearance.secondary_color,
                                                    color: data.appearance.text_color
                                                }}
                                            >
                                                I'd be happy to help! What do you need assistance with?
                                            </div>
                                        </div>
                                    </div>

                                    {/* Input Preview */}
                                    <div className="p-3 border-t border-gray-200">
                                        <div className="flex items-center space-x-2">
                                            <div className="flex-1">
                                                <div
                                                    className="px-3 py-2 bg-gray-50 rounded-full text-sm text-gray-500"
                                                    style={{ fontFamily: data.appearance.font_family }}
                                                >
                                                    Ask me anything...
                                                </div>
                                            </div>
                                            <div
                                                className="w-8 h-8 rounded-full flex items-center justify-center"
                                                style={{ backgroundColor: data.appearance.primary_color }}
                                            >
                                                <span className="text-white text-xs">➤</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Settings Preview Info */}
                                <div className="mt-4 text-xs text-gray-500 space-y-1">
                                    <div>Font: {data.appearance.font_family}</div>
                                    <div>Position: {data.appearance.position}</div>
                                    <div>Border Radius: {data.appearance.border_radius}px</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            {/* Toast Container */}
            <ToastContainer
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                newestOnTop={false}
                closeOnClick
                rtl={false}
                pauseOnFocusLoss
                draggable
                pauseOnHover
                theme="light"
            />
        </AppLayout>
    );
}

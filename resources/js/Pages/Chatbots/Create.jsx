import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function CreateChatbot() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        welcome_message: 'Hello! How can I help you today?',
        initial_message: "Hello! I'm your AI assistant for this knowledge base. I'm here to help answer questions based on the information provided. How can I assist you today?",
        appearance: {
            primary_color: '#4F46E5',
            text_color: '#1F2937',
            background_color: '#FFFFFF',
        },
    });

    const submit = (e) => {
        e.preventDefault();
        post('/chatbots');
    };

    return (
        <AppLayout title="Create Chatbot">
            <div className="px-4 py-6 sm:px-0">
                <div className="md:grid md:grid-cols-3 md:gap-6">
                    <div className="md:col-span-1">
                        <div className="px-4 sm:px-0">
                            <h3 className="text-base font-semibold leading-6 text-gray-900">
                                Chatbot Information
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Create a new AI chatbot with custom knowledge base.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 md:col-span-2 md:mt-0">
                        <form onSubmit={submit}>
                            <div className="shadow sm:overflow-hidden sm:rounded-md">
                                <div className="space-y-6 bg-white px-4 py-5 sm:p-6">
                                    <div>
                                        <label htmlFor="name" className="block text-sm font-medium leading-6 text-gray-900">
                                            Name
                                        </label>
                                        <div className="mt-2">
                                            <input
                                                type="text"
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                placeholder="My AI Assistant"
                                            />
                                            {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label htmlFor="description" className="block text-sm font-medium leading-6 text-gray-900">
                                            Description
                                        </label>
                                        <div className="mt-2">
                                            <textarea
                                                id="description"
                                                rows={3}
                                                value={data.description}
                                                onChange={(e) => setData('description', e.target.value)}
                                                className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                placeholder="A helpful AI assistant that answers questions about..."
                                            />
                                            {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label htmlFor="welcome_message" className="block text-sm font-medium leading-6 text-gray-900">
                                            Welcome Message
                                        </label>
                                        <div className="mt-2">
                                            <textarea
                                                id="welcome_message"
                                                rows={2}
                                                value={data.welcome_message}
                                                onChange={(e) => setData('welcome_message', e.target.value)}
                                                className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                placeholder="Hello! How can I help you today?"
                                            />
                                            {errors.welcome_message && <p className="mt-2 text-sm text-red-600">{errors.welcome_message}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label htmlFor="initial_message" className="block text-sm font-medium leading-6 text-gray-900">
                                            Initial Chat Message
                                        </label>
                                        <div className="mt-2">
                                            <textarea
                                                id="initial_message"
                                                rows={3}
                                                value={data.initial_message}
                                                onChange={(e) => setData('initial_message', e.target.value)}
                                                className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                                placeholder="Hello! I'm your AI assistant for this knowledge base. I'm here to help answer questions based on the information provided. How can I assist you today?"
                                            />
                                            <p className="mt-1 text-sm text-gray-500">This message will be shown as the first message when users start chatting with your bot.</p>
                                            {errors.initial_message && <p className="mt-2 text-sm text-red-600">{errors.initial_message}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="text-sm font-medium leading-6 text-gray-900 mb-4">
                                            Appearance Settings
                                        </h4>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                            <div>
                                                <label htmlFor="primary_color" className="block text-sm font-medium leading-6 text-gray-900">
                                                    Primary Color
                                                </label>
                                                <div className="mt-2">
                                                    <input
                                                        type="color"
                                                        id="primary_color"
                                                        value={data.appearance.primary_color}
                                                        onChange={(e) => setData('appearance', {
                                                            ...data.appearance,
                                                            primary_color: e.target.value
                                                        })}
                                                        className="block w-full h-10 rounded-md border-0 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <label htmlFor="text_color" className="block text-sm font-medium leading-6 text-gray-900">
                                                    Text Color
                                                </label>
                                                <div className="mt-2">
                                                    <input
                                                        type="color"
                                                        id="text_color"
                                                        value={data.appearance.text_color}
                                                        onChange={(e) => setData('appearance', {
                                                            ...data.appearance,
                                                            text_color: e.target.value
                                                        })}
                                                        className="block w-full h-10 rounded-md border-0 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <label htmlFor="background_color" className="block text-sm font-medium leading-6 text-gray-900">
                                                    Background Color
                                                </label>
                                                <div className="mt-2">
                                                    <input
                                                        type="color"
                                                        id="background_color"
                                                        value={data.appearance.background_color}
                                                        onChange={(e) => setData('appearance', {
                                                            ...data.appearance,
                                                            background_color: e.target.value
                                                        })}
                                                        className="block w-full h-10 rounded-md border-0 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="bg-gray-50 px-4 py-3 text-right sm:px-6">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center rounded-md bg-indigo-600 py-2 px-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                                    >
                                        {processing ? 'Creating...' : 'Create Chatbot'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
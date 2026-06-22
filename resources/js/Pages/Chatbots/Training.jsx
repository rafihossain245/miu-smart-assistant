import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import { PlusIcon, TrashIcon, SparklesIcon, AcademicCapIcon, PhotoIcon, DocumentTextIcon, ArrowUpTrayIcon } from '@heroicons/react/24/outline';
import toast, { Toaster } from 'react-hot-toast';

export default function ChatbotTraining({ chatbot, queryExamples = [] }) {
    const [showAddForm, setShowAddForm] = useState(false);
    const [showConversationImport, setShowConversationImport] = useState(false);
    const [conversationText, setConversationText] = useState('');
    const [conversationFile, setConversationFile] = useState(null);
    const [importProcessing, setImportProcessing] = useState(false);
    const [aiProcessing, setAiProcessing] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        customer_query_examples: queryExamples.length > 0 ? queryExamples : [{ question: '', answer: '' }],
    });

    const submit = (e) => {
        e.preventDefault();

        // Filter out empty examples
        const validExamples = data.customer_query_examples.filter(
            example => example.question?.trim() && example.answer?.trim()
        );

        if (validExamples.length === 0) {
            toast.error('Please add at least one complete question-answer pair.');
            return;
        }

        post(`/chatbots/${chatbot.id}/training`, {
            data: { customer_query_examples: validExamples },
            onSuccess: () => {
                toast.success('Training data saved successfully!');
            },
            onError: () => {
                toast.error('Failed to save training data. Please try again.');
            }
        });
    };

    const addExample = () => {
        setData('customer_query_examples', [...data.customer_query_examples, { question: '', answer: '' }]);
    };

    const removeExample = (index) => {
        const examples = data.customer_query_examples.filter((_, i) => i !== index);
        setData('customer_query_examples', examples);
    };

    const updateExample = (index, field, value) => {
        const examples = [...data.customer_query_examples];
        examples[index] = { ...examples[index], [field]: value };
        setData('customer_query_examples', examples);
    };

    const feedToAI = async () => {
        const validExamples = data.customer_query_examples.filter(
            example => example.question?.trim() && example.answer?.trim()
        );

        if (validExamples.length === 0) {
            toast.error('Please add some query examples first.');
            return;
        }

        setAiProcessing(true);
        const loadingToast = toast.loading('Feeding data to AI for training...');

        try {
            const response = await fetch(`/chatbots/${chatbot.id}/feed-query-examples`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    query_examples: validExamples
                })
            });

            const result = await response.json();

            toast.dismiss(loadingToast);

            if (result.success) {
                toast.success(result.message);
            } else {
                toast.error(result.message);
            }
        } catch (error) {
            toast.dismiss(loadingToast);
            toast.error('Failed to feed data to AI: ' + error.message);
        } finally {
            setAiProcessing(false);
        }
    };

    const importConversations = async () => {
        if (!conversationText.trim() && !conversationFile) {
            toast.error('Please provide conversation text or upload an image.');
            return;
        }

        setImportProcessing(true);
        const loadingToast = toast.loading('Processing conversation data...');

        try {
            const formData = new FormData();

            if (conversationFile) {
                formData.append('conversation_file', conversationFile);
            }

            if (conversationText.trim()) {
                formData.append('conversation_text', conversationText);
            }

            const response = await fetch(`/chatbots/${chatbot.id}/import-conversations`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: formData
            });

            const result = await response.json();

            toast.dismiss(loadingToast);

            if (result.success) {
                toast.success(result.message);
                if (result.extracted_examples) {
                    // Add extracted examples to current examples
                    const newExamples = [...data.customer_query_examples, ...result.extracted_examples];
                    setData('customer_query_examples', newExamples);
                }
                // Reset form
                setConversationText('');
                setConversationFile(null);
                setShowConversationImport(false);
            } else {
                toast.error(result.message);
            }
        } catch (error) {
            toast.dismiss(loadingToast);
            toast.error('Failed to import conversations: ' + error.message);
        } finally {
            setImportProcessing(false);
        }
    };

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain'];
            if (!validTypes.includes(file.type)) {
                toast.error('Please upload a valid image (JPG, PNG, GIF, WebP) or text file.');
                return;
            }
            setConversationFile(file);
            toast.success(`File "${file.name}" selected successfully!`);
        }
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Response Training - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                <div className="sm:flex sm:items-center">
                    <div className="sm:flex-auto">
                        <h1 className="text-base font-semibold leading-6 text-gray-900">
                            Response Training for {chatbot.name}
                        </h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Add examples of common questions your customers ask and the ideal responses. This helps train the AI for better accuracy.
                        </p>
                    </div>
                </div>

                {/* Response Training Form */}
                <div className="mt-8 bg-white shadow sm:rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="flex items-center justify-between mb-6">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">Customer Query Examples</h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Add examples of questions customers ask and your ideal responses
                                </p>
                            </div>
                            <div className="flex space-x-3">
                                <button
                                    type="button"
                                    onClick={addExample}
                                    className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <PlusIcon className="h-4 w-4 mr-2" />
                                    Add Example
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        console.log('Import Conversations clicked, current state:', showConversationImport);
                                        setShowConversationImport(!showConversationImport);
                                    }}
                                    className="inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm leading-4 font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                >
                                    <ArrowUpTrayIcon className="h-4 w-4 mr-2" />
                                    {showConversationImport ? 'Hide Import' : 'Import Conversations'}
                                </button>
                                <button
                                    type="button"
                                    onClick={feedToAI}
                                    disabled={aiProcessing}
                                    className="inline-flex items-center px-3 py-2 border border-indigo-300 shadow-sm text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {aiProcessing ? (
                                        <>
                                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-700 mr-2"></div>
                                            Processing...
                                        </>
                                    ) : (
                                        <>
                                            <SparklesIcon className="h-4 w-4 mr-2" />
                                            Feed to AI for Response Training
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            {data.customer_query_examples.map((example, index) => (
                                <div key={index} className="border border-gray-200 rounded-lg p-4">
                                    <div className="flex items-center justify-between mb-3">
                                        <h4 className="text-sm font-medium text-gray-900">
                                            Example {index + 1}
                                        </h4>
                                        {data.customer_query_examples.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeExample(index)}
                                                className="text-red-600 hover:text-red-800"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>

                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Customer Question
                                            </label>
                                            <input
                                                type="text"
                                                value={example.question}
                                                onChange={(e) => updateExample(index, 'question', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="What question might a customer ask?"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">
                                                Ideal Response
                                            </label>
                                            <textarea
                                                rows={3}
                                                value={example.answer}
                                                onChange={(e) => updateExample(index, 'answer', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                placeholder="How should the AI respond to this question?"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <div className="flex justify-end space-x-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? (
                                        <>
                                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                            Saving...
                                        </>
                                    ) : (
                                        'Save Response Training'
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Conversation Import Section */}
                {showConversationImport && (
                    <div className="mt-8 bg-white shadow sm:rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="text-lg font-medium text-gray-900">Import Existing Conversations</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Upload images of conversations or paste conversation text to extract Q&A pairs
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-6">
                                {/* File Upload Section */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Upload Conversation Image
                                    </label>
                                    <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                        <div className="space-y-1 text-center">
                                            <PhotoIcon className="mx-auto h-12 w-12 text-gray-400" />
                                            <div className="flex text-sm text-gray-600">
                                                <label className="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                    <span>Upload a file</span>
                                                    <input
                                                        type="file"
                                                        className="sr-only"
                                                        accept="image/*,text/plain"
                                                        onChange={handleFileChange}
                                                    />
                                                </label>
                                                <p className="pl-1">or drag and drop</p>
                                            </div>
                                            <p className="text-xs text-gray-500">
                                                PNG, JPG, GIF, WebP up to 10MB or text files
                                            </p>
                                        </div>
                                    </div>
                                    {conversationFile && (
                                        <div className="mt-2 flex items-center text-sm text-green-600">
                                            <DocumentTextIcon className="h-4 w-4 mr-1" />
                                            {conversationFile.name}
                                        </div>
                                    )}
                                </div>

                                {/* Text Input Section */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Or Paste Conversation Text
                                    </label>
                                    <textarea
                                        rows={6}
                                        value={conversationText}
                                        onChange={(e) => setConversationText(e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        placeholder={`Paste your conversation here. Example format:

Customer: What are your prices?
Support: Our basic plan starts at $29/month...

Customer: Do you offer refunds?
Support: Yes, we offer a 30-day money-back guarantee...`}
                                    />
                                </div>

                                {/* Action Buttons */}
                                <div className="flex justify-end space-x-3">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowConversationImport(false);
                                            setConversationText('');
                                            setConversationFile(null);
                                        }}
                                        className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={importConversations}
                                        disabled={importProcessing || (!conversationText.trim() && !conversationFile)}
                                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                    >
                                        {importProcessing ? 'Processing...' : 'Import & Extract Q&A'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Training Tips */}
                <div className="mt-8 bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <AcademicCapIcon className="h-5 w-5 text-blue-400" />
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-blue-900">Training Tips</h3>
                            <div className="mt-2 text-sm text-blue-700">
                                <ul className="list-disc list-inside space-y-1">
                                    <li>Add real questions your customers frequently ask</li>
                                    <li>Write clear, helpful responses that match your brand voice</li>
                                    <li>Include specific program/service names in responses when relevant</li>
                                    <li>Use the "Feed to AI" button to improve response accuracy</li>
                                    <li>Add 5-10 examples for best results</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Toast Notifications */}
            <Toaster
                position="top-right"
                toastOptions={{
                    duration: 4000,
                    style: {
                        background: '#363636',
                        color: '#fff',
                    },
                    success: {
                        style: {
                            background: '#10b981',
                        },
                    },
                    error: {
                        style: {
                            background: '#ef4444',
                        },
                    },
                    loading: {
                        style: {
                            background: '#3b82f6',
                        },
                    },
                }}
            />
        </ChatbotLayout>
    );
}

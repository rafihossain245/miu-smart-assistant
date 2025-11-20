import React, { useState, useEffect } from 'react';
import { useForm, Link, router } from '@inertiajs/react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import Pagination from '../../Components/Pagination';
import { PlusIcon, DocumentTextIcon, LinkIcon, FilmIcon, ChatBubbleLeftRightIcon, ArrowPathIcon, MapIcon, QueueListIcon, WrenchScrewdriverIcon } from '@heroicons/react/24/outline';
import { useEcho } from '../../contexts/EchoContext';

const sourceTypeIcons = {
    url: LinkIcon,
    pdf: DocumentTextIcon,
    youtube: FilmIcon,
    text: ChatBubbleLeftRightIcon,
    sitemap: MapIcon,
    youtube_playlist: QueueListIcon,
    technical_issue: WrenchScrewdriverIcon,
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
};

export default function ChatbotSources({ chatbot, sources }) {
    const [showAddForm, setShowAddForm] = useState(false);
    const [selectedType, setSelectedType] = useState('url');
    const [syncingAll, setSyncingAll] = useState(false);
    const [syncingIds, setSyncingIds] = useState([]);
    const [successMessage, setSuccessMessage] = useState('');
    const [errorMessage, setErrorMessage] = useState('');
    const [liveSources, setLiveSources] = useState(sources.data);
    const { echo, isConnected } = useEcho();

    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'url',
        title: '',
        url: '',
        content: '',
        file: null,
    });

    // Listen for real-time source updates
    useEffect(() => {
        if (!echo || !isConnected) return;

        const channel = echo.private(`chatbot.${chatbot.id}`);

        const handleSourceUpdate = (e) => {
            setLiveSources(prevSources =>
                prevSources.map(source =>
                    source.id === e.source.id
                        ? { ...source, ...e.source }
                        : source
                )
            );

            // Update syncing states
            setSyncingIds(prevIds => prevIds.filter(id => id !== e.source.id));

            // Show notification
            if (e.source.status === 'completed') {
                setSuccessMessage(`${e.source.title} processed successfully!`);
                setTimeout(() => setSuccessMessage(''), 4000);
            } else if (e.source.status === 'failed') {
                setErrorMessage(`Failed to process ${e.source.title}: ${e.source.error_message || 'Unknown error'}`);
                setTimeout(() => setErrorMessage(''), 5000);
            }
        };

        const handleSourceProcessingStarted = (e) => {
            setLiveSources(prevSources =>
                prevSources.map(source =>
                    source.id === e.source.id
                        ? { ...source, status: 'processing' }
                        : source
                )
            );
        };

        const handleSourceCreated = (e) => {
            // Add new source to the list (for sitemap imports and new source additions)
            setLiveSources(prevSources => {
                // Check if source already exists to avoid duplicates
                const exists = prevSources.some(source => source.id === e.source.id);
                if (exists) {
                    return prevSources;
                }
                // Add new source to the beginning of the list
                return [e.source, ...prevSources];
            });

            // Show notification for new source added
            setSuccessMessage(`New source "${e.source.title}" has been added!`);
            setTimeout(() => setSuccessMessage(''), 4000);
        };

        channel.listen('.source.created', handleSourceCreated);
        channel.listen('.source.processing.started', handleSourceProcessingStarted);
        channel.listen('.source.processing.completed', handleSourceUpdate);
        channel.listen('.source.processing.failed', handleSourceUpdate);

        return () => {
            channel.stopListening('.source.created');
            channel.stopListening('.source.processing.started');
            channel.stopListening('.source.processing.completed');
            channel.stopListening('.source.processing.failed');
        };
    }, [echo, isConnected, chatbot.id]);

    // Update liveSources when sources prop changes (e.g., page refresh)
    useEffect(() => {
        setLiveSources(sources.data);
    }, [sources.data]);

    const submit = (e) => {
        e.preventDefault();

        post(`/chatbots/${chatbot.id}/sources`, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setShowAddForm(false);
            }
        });
    };

    const handleTypeChange = (type) => {
        setSelectedType(type);
        setData('type', type);
    };

    const syncSource = async (sourceId) => {
        setSyncingIds(prev => [...prev, sourceId]);
        setSuccessMessage('');
        setErrorMessage('');

        try {
            await router.post(`/chatbots/${chatbot.id}/sources/${sourceId}/sync`, {}, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setSuccessMessage(page.props.flash?.message || 'Source fetch initiated successfully! Content will be updated shortly.');
                    setTimeout(() => setSuccessMessage(''), 4000);
                },
                onError: (errors) => {
                    setErrorMessage('Failed to sync source. Please try again.');
                    setTimeout(() => setErrorMessage(''), 3000);
                }
            });
        } finally {
            setSyncingIds(prev => prev.filter(id => id !== sourceId));
        }
    };

    const syncAllSources = async () => {
        setSyncingAll(true);
        setSuccessMessage('');
        setErrorMessage('');

        try {
            await router.post(`/chatbots/${chatbot.id}/sources/sync-all`, {}, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setSuccessMessage(page.props.flash?.message || 'Fetch initiated for all sources! Content will be refreshed shortly.');
                    setTimeout(() => setSuccessMessage(''), 4000);
                },
                onError: (errors) => {
                    setErrorMessage('Failed to sync all sources. Please try again.');
                    setTimeout(() => setErrorMessage(''), 3000);
                }
            });
        } finally {
            setSyncingAll(false);
        }
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Knowledge Base - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                <div className="sm:flex sm:items-center">
                    <div className="sm:flex-auto">
                        <h1 className="text-base font-semibold leading-6 text-gray-900">
                            Knowledge Base for {chatbot.name}
                        </h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Manage your chatbot's knowledge sources. Add URLs, PDFs, YouTube videos, or direct text.
                        </p>
                        {echo && (
                            <p className="mt-1 text-xs text-gray-500 flex items-center">
                                <span className={`w-2 h-2 rounded-full mr-2 ${isConnected ? 'bg-green-400' : 'bg-red-400'}`}></span>
                                Real-time updates {isConnected ? 'connected' : 'disconnected'}
                            </p>
                        )}
                    </div>
                    <div className="mt-4 sm:ml-16 sm:mt-0 sm:flex-none flex space-x-3">
                        <button
                            onClick={syncAllSources}
                            disabled={syncingAll}
                            className="rounded-md bg-green-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50"
                        >
                            <ArrowPathIcon className={`h-4 w-4 inline mr-1 ${syncingAll ? 'animate-spin' : ''}`} />
                            {syncingAll ? 'Fetching All...' : 'Fetch All'}
                        </button>
                        <button
                            onClick={() => setShowAddForm(!showAddForm)}
                            className="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        >
                            <PlusIcon className="h-4 w-4 inline mr-1" />
                            Add Source
                        </button>
                    </div>
                </div>

                {/* Success/Error Messages */}
                {successMessage && (
                    <div className="mt-4 rounded-md bg-green-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-green-800">{successMessage}</p>
                            </div>
                        </div>
                    </div>
                )}

                {errorMessage && (
                    <div className="mt-4 rounded-md bg-red-50 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm font-medium text-red-800">{errorMessage}</p>
                            </div>
                        </div>
                    </div>
                )}

                {showAddForm && (
                    <div className="mt-8 bg-white shadow sm:rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <h3 className="text-base font-semibold leading-6 text-gray-900">Add New Source</h3>
                            <div className="mt-4">
                                {/* Source Type Selection */}
                                <div className="mb-6">
                                    <label className="text-sm font-medium text-gray-900">Source Type</label>
                                    <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        {[
                                            { key: 'url', label: 'Website URL', icon: LinkIcon },
                                            { key: 'pdf', label: 'PDF Upload', icon: DocumentTextIcon },
                                            { key: 'youtube', label: 'YouTube Video', icon: FilmIcon },
                                            { key: 'youtube_playlist', label: 'YouTube Playlist', icon: QueueListIcon },
                                            { key: 'text', label: 'Direct Text', icon: ChatBubbleLeftRightIcon },
                                            { key: 'sitemap', label: 'Sitemap URLs', icon: MapIcon },
                                            { key: 'technical_issue', label: 'Technical Issue', icon: WrenchScrewdriverIcon },
                                        ].map((type) => {
                                            const Icon = type.icon;
                                            return (
                                                <button
                                                    key={type.key}
                                                    onClick={() => handleTypeChange(type.key)}
                                                    className={`relative rounded-lg border p-4 focus:outline-none ${
                                                        selectedType === type.key
                                                            ? 'bg-indigo-50 border-indigo-200'
                                                            : 'bg-white border-gray-300 hover:border-gray-400'
                                                    }`}
                                                >
                                                    <Icon className="h-6 w-6 mx-auto text-gray-400" />
                                                    <span className="mt-2 block text-sm font-medium text-gray-900">
                                                        {type.label}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <form onSubmit={submit} className="space-y-4">
                                    <div>
                                        <label htmlFor="title" className="block text-sm font-medium text-gray-700">
                                            Title
                                        </label>
                                        <input
                                            type="text"
                                            id="title"
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            placeholder="Enter a descriptive title"
                                        />
                                        {errors.title && <p className="mt-2 text-sm text-red-600">{errors.title}</p>}
                                    </div>

                                    {selectedType === 'url' && (
                                        <div>
                                            <label htmlFor="url" className="block text-sm font-medium text-gray-700">
                                                Website URL
                                            </label>
                                            <input
                                                type="url"
                                                id="url"
                                                value={data.url}
                                                onChange={(e) => setData('url', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="https://example.com/documentation"
                                            />
                                            {errors.url && <p className="mt-2 text-sm text-red-600">{errors.url}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'youtube' && (
                                        <div>
                                            <label htmlFor="url" className="block text-sm font-medium text-gray-700">
                                                YouTube Video URL
                                            </label>
                                            <input
                                                type="url"
                                                id="url"
                                                value={data.url}
                                                onChange={(e) => setData('url', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="https://youtube.com/watch?v=..."
                                            />
                                            {errors.url && <p className="mt-2 text-sm text-red-600">{errors.url}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'youtube_playlist' && (
                                        <div>
                                            <label htmlFor="url" className="block text-sm font-medium text-gray-700">
                                                YouTube Playlist URL
                                            </label>
                                            <input
                                                type="url"
                                                id="url"
                                                value={data.url}
                                                onChange={(e) => setData('url', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="https://youtube.com/playlist?list=... or https://youtube.com/watch?v=...&list=..."
                                            />
                                            <p className="mt-1 text-xs text-gray-500">
                                                Enter a YouTube playlist URL. Each video in the playlist will be processed as an individual source (limited to 50 videos).
                                            </p>
                                            {errors.url && <p className="mt-2 text-sm text-red-600">{errors.url}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'sitemap' && (
                                        <div>
                                            <label htmlFor="url" className="block text-sm font-medium text-gray-700">
                                                Sitemap URL
                                            </label>
                                            <input
                                                type="url"
                                                id="url"
                                                value={data.url}
                                                onChange={(e) => setData('url', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="https://example.com/sitemap.xml"
                                            />
                                            <p className="mt-1 text-xs text-gray-500">
                                                Enter a sitemap XML URL. The system will crawl all URLs listed in the sitemap and add them as individual sources.
                                            </p>
                                            {errors.url && <p className="mt-2 text-sm text-red-600">{errors.url}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'pdf' && (
                                        <div>
                                            <label htmlFor="file" className="block text-sm font-medium text-gray-700">
                                                PDF File
                                            </label>
                                            <input
                                                type="file"
                                                id="file"
                                                accept=".pdf"
                                                onChange={(e) => setData('file', e.target.files[0])}
                                                className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                            />
                                            {errors.file && <p className="mt-2 text-sm text-red-600">{errors.file}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'text' && (
                                        <div>
                                            <label htmlFor="content" className="block text-sm font-medium text-gray-700">
                                                Text Content
                                            </label>
                                            <textarea
                                                id="content"
                                                rows={6}
                                                value={data.content}
                                                onChange={(e) => setData('content', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder="Enter your content here..."
                                            />
                                            {errors.content && <p className="mt-2 text-sm text-red-600">{errors.content}</p>}
                                        </div>
                                    )}

                                    {selectedType === 'technical_issue' && (
                                        <div>
                                            <label htmlFor="content" className="block text-sm font-medium text-gray-700">
                                                Problem & Solution
                                            </label>
                                            <textarea
                                                id="content"
                                                rows={8}
                                                value={data.content}
                                                onChange={(e) => setData('content', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                                placeholder={`Example format:

Problem: Update button freezes when clicking "Check for update" after upgrading to PHP 8.3

Solution:
1. Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
2. Clear application cache:
   - Login to hosting panel
   - Go to File Manager → @core/bootstrap/cache
   - Delete all cache files
3. Check error logs for PHP errors
4. Verify PHP 8.3 compatibility

Additional Notes: This commonly occurs after PHP version upgrades.`}
                                            />
                                            <p className="mt-1 text-xs text-gray-500">
                                                Provide a structured problem description and step-by-step solution. This will help the AI provide accurate troubleshooting guidance.
                                            </p>
                                            {errors.content && <p className="mt-2 text-sm text-red-600">{errors.content}</p>}
                                        </div>
                                    )}

                                    <div className="flex justify-end space-x-3">
                                        <button
                                            type="button"
                                            onClick={() => setShowAddForm(false)}
                                            className="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                        >
                                            {processing ? 'Adding...' : 'Add Source'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                )}

                {/* Sources List */}
                <div className="mt-8">
                    {liveSources.length === 0 ? (
                        <div className="text-center py-12">
                            <DocumentTextIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <h3 className="mt-2 text-sm font-semibold text-gray-900">No sources</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Get started by adding your first knowledge source.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="bg-white shadow overflow-hidden sm:rounded-md">
                                <ul className="divide-y divide-gray-200">
                                    {liveSources.map((source) => {
                                        const Icon = sourceTypeIcons[source.type];
                                        return (
                                            <li key={source.id}>
                                                <div className="px-4 py-4 flex items-center justify-between">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0">
                                                            <Icon className="h-6 w-6 text-gray-400" />
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {source.title}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {source.type === 'technical_issue'
                                                                    ? 'Technical Issue'
                                                                    : source.type.charAt(0).toUpperCase() + source.type.slice(1).replace('_', ' ')
                                                                }
                                                                {source.url && ` • ${source.url}`}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center space-x-2">
                                                        <button
                                                            onClick={() => syncSource(source.id)}
                                                            disabled={syncingIds.includes(source.id)}
                                                            className={`inline-flex items-center px-2 py-1 rounded text-xs font-medium disabled:opacity-50 ${
                                                                source.status === 'completed'
                                                                    ? 'text-blue-700 bg-blue-50 hover:bg-blue-100'
                                                                    : source.status === 'failed'
                                                                    ? 'text-red-700 bg-red-50 hover:bg-red-100'
                                                                    : 'text-green-700 bg-green-50 hover:bg-green-100'
                                                            }`}
                                                            title={source.status === 'completed' ? 'Fetch fresh content and resync' : 'Fetch content and sync with AI'}
                                                        >
                                                            <ArrowPathIcon className={`h-3 w-3 mr-1 ${syncingIds.includes(source.id) ? 'animate-spin' : ''}`} />
                                                            {syncingIds.includes(source.id)
                                                                ? 'Fetching...'
                                                                : source.status === 'completed'
                                                                ? 'Refetch'
                                                                : source.status === 'failed'
                                                                ? 'Retry'
                                                                : 'Fetch'
                                                            }
                                                        </button>
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[source.status]}`}>
                                                            {source.status.charAt(0).toUpperCase() + source.status.slice(1)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                            {/* Pagination */}
                            <Pagination data={sources} className="mt-6" />
                        </>
                    )}
                </div>
            </div>
        </ChatbotLayout>
    );
}
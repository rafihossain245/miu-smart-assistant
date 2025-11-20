import React, { useState } from 'react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import { ClipboardDocumentIcon, CheckIcon } from '@heroicons/react/24/outline';

export default function ChatbotEmbed({ chatbot }) {
    const [copied, setCopied] = useState(false);
    const [embedType, setEmbedType] = useState('widget');

    const baseUrl = window.location.origin;

    const embedCodes = {
        widget: `<!-- AI Chatbot Widget -->
<script>
  window.aiChatbot = {
    chatbotId: '${chatbot.id}',
    baseUrl: '${baseUrl}',
    config: {
      position: 'bottom-right',
      primaryColor: '${chatbot.appearance?.primary_color || '#4F46E5'}',
      textColor: '${chatbot.appearance?.text_color || '#1F2937'}',
      backgroundColor: '${chatbot.appearance?.background_color || '#FFFFFF'}',
      welcomeMessage: '${chatbot.welcome_message}'
    }
  };
</script>
<script src="${baseUrl}/chatbot-widget.js"></script>`,

        iframe: `<!-- AI Chatbot iFrame -->
<iframe
  src="${baseUrl}/embed/${chatbot.id}"
  width="100%"
  height="600"
  frameborder="0"
  style="border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"
></iframe>`,

        api: `// AI Chatbot API Integration
fetch('${baseUrl}/api/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    message: 'Your question here',
    chatbot_id: '${chatbot.id}',
    session_id: 'unique-session-id' // Optional
  })
})
.then(response => response.json())
.then(data => {
  console.log('Reply:', data.reply);
  console.log('Sources:', data.sources);
})
.catch(error => console.error('Error:', error));`
    };

    const copyToClipboard = async (text) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Embed Code - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                <div className="mb-6">
                    <h1 className="text-base font-semibold leading-6 text-gray-900">
                        Embed {chatbot.name}
                    </h1>
                    <p className="mt-2 text-sm text-gray-700">
                        Choose how you want to embed your chatbot on your website.
                    </p>
                </div>

                {/* Embed Type Selection */}
                <div className="mb-8">
                    <div className="sm:hidden">
                        <label htmlFor="embed-type" className="sr-only">
                            Select embed type
                        </label>
                        <select
                            id="embed-type"
                            name="embed-type"
                            value={embedType}
                            onChange={(e) => setEmbedType(e.target.value)}
                            className="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="widget">Chat Widget</option>
                            <option value="iframe">iFrame Embed</option>
                            <option value="api">API Integration</option>
                        </select>
                    </div>
                    <div className="hidden sm:block">
                        <nav className="flex space-x-8" aria-label="Tabs">
                            {[
                                { key: 'widget', name: 'Chat Widget', description: 'Floating chat button' },
                                { key: 'iframe', name: 'iFrame Embed', description: 'Full chat interface' },
                                { key: 'api', name: 'API Integration', description: 'Custom implementation' },
                            ].map((tab) => (
                                <button
                                    key={tab.key}
                                    onClick={() => setEmbedType(tab.key)}
                                    className={`whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm ${
                                        embedType === tab.key
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    {tab.name}
                                    <span className="ml-2 text-xs text-gray-400">
                                        {tab.description}
                                    </span>
                                </button>
                            ))}
                        </nav>
                    </div>
                </div>

                {/* Embed Code Display */}
                <div className="bg-white shadow sm:rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-lg font-medium leading-6 text-gray-900">
                                {embedType === 'widget' && 'Chat Widget Code'}
                                {embedType === 'iframe' && 'iFrame Embed Code'}
                                {embedType === 'api' && 'API Integration Code'}
                            </h3>
                            <button
                                onClick={() => copyToClipboard(embedCodes[embedType])}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {copied ? (
                                    <>
                                        <CheckIcon className="h-4 w-4 mr-2" />
                                        Copied!
                                    </>
                                ) : (
                                    <>
                                        <ClipboardDocumentIcon className="h-4 w-4 mr-2" />
                                        Copy Code
                                    </>
                                )}
                            </button>
                        </div>

                        <div className="relative">
                            <pre className="bg-gray-50 p-4 rounded-md text-sm overflow-x-auto border">
                                <code className="text-gray-800">
                                    {embedCodes[embedType]}
                                </code>
                            </pre>
                        </div>

                        {/* Instructions */}
                        <div className="mt-6">
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Instructions:</h4>

                            {embedType === 'widget' && (
                                <div className="text-sm text-gray-600 space-y-2">
                                    <p>1. Copy the code above and paste it just before the closing &lt;/body&gt; tag on your website.</p>
                                    <p>2. The chat widget will appear as a floating button in the bottom-right corner.</p>
                                    <p>3. Visitors can click the button to open the chat interface.</p>
                                    <p>4. The widget will use your chatbot's appearance settings automatically.</p>
                                </div>
                            )}

                            {embedType === 'iframe' && (
                                <div className="text-sm text-gray-600 space-y-2">
                                    <p>1. Copy the iframe code and paste it where you want the chat interface to appear.</p>
                                    <p>2. Adjust the width and height attributes as needed for your layout.</p>
                                    <p>3. The iframe will display a full chat interface with your chatbot.</p>
                                    <p>4. This option provides more control over placement and sizing.</p>
                                </div>
                            )}

                            {embedType === 'api' && (
                                <div className="text-sm text-gray-600 space-y-2">
                                    <p>1. Use this code to integrate the chatbot API into your custom application.</p>
                                    <p>2. Replace 'Your question here' with the user's actual message.</p>
                                    <p>3. The API returns both the reply and source citations.</p>
                                    <p>4. Include a unique session_id to track conversation context.</p>
                                </div>
                            )}
                        </div>

                        {/* Preview */}
                        {embedType !== 'api' && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <h4 className="text-sm font-medium text-gray-900 mb-4">Preview:</h4>

                                {embedType === 'widget' && (
                                    <div className="bg-gray-100 p-4 rounded-md">
                                        <div className="flex justify-end">
                                            <div
                                                className="w-14 h-14 rounded-full flex items-center justify-center text-white font-semibold shadow-lg cursor-pointer"
                                                style={{ backgroundColor: chatbot.appearance?.primary_color || '#4F46E5' }}
                                            >
                                                💬
                                            </div>
                                        </div>
                                        <p className="text-xs text-gray-500 mt-2 text-right">
                                            Chat widget will appear here
                                        </p>
                                    </div>
                                )}

                                {embedType === 'iframe' && (
                                    <div className="bg-gray-100 p-4 rounded-md">
                                        <div
                                            className="bg-white rounded-lg shadow border p-4 h-40 flex items-center justify-center"
                                            style={{
                                                borderColor: chatbot.appearance?.primary_color || '#4F46E5',
                                                borderWidth: '2px'
                                            }}
                                        >
                                            <div className="text-center">
                                                <div className="text-2xl mb-2">💬</div>
                                                <p className="text-sm font-medium" style={{ color: chatbot.appearance?.text_color || '#1F2937' }}>
                                                    {chatbot.name}
                                                </p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Full chat interface preview
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Additional Settings */}
                <div className="mt-8 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <h3 className="text-sm font-medium text-yellow-900">Important Notes</h3>
                    <div className="mt-2 text-sm text-yellow-700">
                        <ul className="list-disc list-inside space-y-1">
                            <li>Make sure your chatbot is active and has knowledge sources added</li>
                            <li>The chatbot will only answer questions based on your knowledge base</li>
                            <li>Test your chatbot thoroughly before embedding on your live website</li>
                            <li>You can customize the appearance from the chatbot settings page</li>
                        </ul>
                    </div>
                </div>
            </div>
        </ChatbotLayout>
    );
}
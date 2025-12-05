import React, { useState, useRef, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { PaperAirplaneIcon, XMarkIcon, ChatBubbleLeftRightIcon } from '@heroicons/react/24/outline';

export default function EmbedView({ chatbot }) {
    // Session management
    const SESSION_EXPIRY_DAYS = 7;
    const SESSION_KEY = `ai_chatbot_embed_session_${chatbot.id}`;
    
    const [sessionId, setSessionId] = useState(null);
    const [isLoadingHistory, setIsLoadingHistory] = useState(true);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(true); // Always open when embedded
    const messagesEndRef = useRef(null);

    // Check if we're in an iframe (embedded)
    const isEmbedded = window.self !== window.top;

    // Initialize session and load history on mount
    useEffect(() => {
        initializeSession();
    }, []);

    const initializeSession = async () => {
        try {
            // Check for existing session
            const storedSession = localStorage.getItem(SESSION_KEY);
            let currentSessionId;

            if (storedSession) {
                const sessionData = JSON.parse(storedSession);
                const expiryDate = new Date(sessionData.expiry);

                {console.log(sessionData, 'expire',expiryDate);}
                
                // Check if session is still valid
                if (expiryDate > new Date()) {
                    currentSessionId = sessionData.sessionId;
                    console.log('Existing embed session found:', currentSessionId);
                    
                    // Load previous messages
                    await loadConversationHistory(currentSessionId);
                } else {
                    console.log('Embed session expired, creating new session');
                    currentSessionId = createNewSession();
                    setMessages([{
                        id: 1,
                        content: chatbot.welcome_message || "Hello! How can I help you today?",
                        isBot: true,
                        timestamp: new Date(),
                        sources: []
                    }]);
                }
            } else {
                console.log('No embed session found, creating new session');
                currentSessionId = createNewSession();
                setMessages([{
                    id: 1,
                    content: chatbot.welcome_message || "Hello! How can I help you today?",
                    isBot: true,
                    timestamp: new Date(),
                    sources: []
                }]);
            }

            setSessionId(currentSessionId);
        } catch (error) {
            console.error('Failed to initialize embed session:', error);
            const newSessionId = createNewSession();
            setSessionId(newSessionId);
            setMessages([{
                id: 1,
                content: chatbot.welcome_message || "Hello! How can I help you today?",
                isBot: true,
                timestamp: new Date(),
                sources: []
            }]);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    const createNewSession = () => {
        const newSessionId = 'embed_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        // Calculate expiry date (7 days from now)
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + SESSION_EXPIRY_DAYS);
        
        // Store session in localStorage
        localStorage.setItem(SESSION_KEY, JSON.stringify({
            sessionId: newSessionId,
            expiry: expiryDate.toISOString()
        }));
        
        return newSessionId;
    };

    const loadConversationHistory = async (sessionId) => {
        try {
            const response = await fetch(`/api/conversation/${sessionId}/messages`);
            const data = await response.json();
            
            if (data.messages && data.messages.length > 0) {
                console.log('Loaded', data.messages.length, 'previous embed messages');
                setMessages(data.messages.map(msg => ({
                    ...msg,
                    timestamp: new Date(msg.timestamp)
                })));
            } else {
                console.log('No previous embed messages found, starting fresh');
                setMessages([{
                    id: 1,
                    content: chatbot.welcome_message || "Hello! How can I help you today?",
                    isBot: true,
                    timestamp: new Date(),
                    sources: []
                }]);
            }
        } catch (error) {
            console.error('Failed to load embed conversation history:', error);
            setMessages([{
                id: 1,
                content: chatbot.welcome_message || "Hello! How can I help you today?",
                isBot: true,
                timestamp: new Date(),
                sources: []
            }]);
        }
    };

    // Function to render bot icon consistently
    const renderBotIcon = (size = 'sm') => {
        const sizeClasses = {
            xs: 'w-4 h-4',
            sm: 'w-5 h-5',
            md: 'w-6 h-6',
            lg: 'w-8 h-8'
        };

        if (chatbot.appearance?.icon_type === 'emoji' && chatbot.appearance?.selected_emoji) {
            const textSizes = {
                xs: 'text-xs',
                sm: 'text-sm',
                md: 'text-base',
                lg: 'text-lg'
            };
            return <span className={`text-white ${textSizes[size]}`}>{chatbot.appearance.selected_emoji}</span>;
        } else if (chatbot.appearance?.icon_type === 'svg' && chatbot.appearance?.selected_svg) {
            const iconName = chatbot.appearance.selected_svg;
            const svgIcons = {
                chat: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clipRule="evenodd" /></svg>,
                support: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clipRule="evenodd" /></svg>,
                robot: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>,
                assistant: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>,
                help: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" /></svg>,
                message: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>
            };
            return svgIcons[iconName] || svgIcons.chat;
        } else if (chatbot.appearance?.icon_type === 'upload' && chatbot.appearance?.custom_icon) {
            return (
                <img
                    src={chatbot.appearance.custom_icon}
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

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const sendMessage = async (e) => {
        e.preventDefault();
        if (!input.trim() || isLoading || !sessionId) return;

        const userMessage = {
            id: Date.now(),
            content: input.trim(),
            isBot: false,
            timestamp: new Date(),
            sources: []
        };

        setMessages(prev => [...prev, userMessage]);
        setInput('');
        setIsLoading(true);

        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    message: userMessage.content,
                    chatbot_id: chatbot.id,
                    session_id: sessionId, // Use persistent session ID
                }),
            });

            const data = await response.json();

            if (response.ok) {
                const botMessage = {
                    id: Date.now() + 1,
                    content: data.reply,
                    isBot: true,
                    timestamp: new Date(),
                    sources: data.sources || []
                };
                setMessages(prev => [...prev, botMessage]);
            } else {
                const errorMessage = {
                    id: Date.now() + 1,
                    content: data.error || 'Sorry, I encountered an error. Please try again.',
                    isBot: true,
                    timestamp: new Date(),
                    sources: []
                };
                setMessages(prev => [...prev, errorMessage]);
            }
        } catch (error) {
            const errorMessage = {
                id: Date.now() + 1,
                content: 'Sorry, I encountered a network error. Please try again.',
                isBot: true,
                timestamp: new Date(),
                sources: []
            };
            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const primaryColor = chatbot.appearance?.primary_color || '#8B5CF6';
    const backgroundColor = chatbot.appearance?.background_color || '#FFFFFF';
    const textColor = chatbot.appearance?.text_color || '#1F2937';
    const borderRadius = chatbot.appearance?.border_radius || '16';

    // Render differently based on whether we're embedded or standalone
    if (isEmbedded) {
        // Embedded view - full iframe, no floating buttons
        return (
            <>
                <Head title={`Chat with ${chatbot.name}`} />

                <div className="h-screen w-full flex flex-col" style={{ backgroundColor: backgroundColor }}>
                    {/* Header */}
                    <div
                        className="p-4 text-white flex items-center justify-between"
                        style={{ backgroundColor: primaryColor }}
                    >
                        <div className="flex items-center space-x-3">
                            <div className="w-10 h-10 bg-black bg-opacity-20 rounded-full flex items-center justify-center">
                                {renderBotIcon('lg')}
                            </div>
                            <div>
                                <h3 className="font-semibold text-sm">
                                    {chatbot.appearance?.header_text || chatbot.name}
                                </h3>
                                <p className="text-xs opacity-90">
                                    {chatbot.appearance?.header_subtitle || 'AI Assistant'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {isLoadingHistory ? (
                            <div className="flex items-center justify-center h-full">
                                <div className="text-center">
                                    <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mb-2"></div>
                                    <p className="text-gray-500 text-sm">Loading conversation...</p>
                                </div>
                            </div>
                        ) : (
                            <>
                                {messages.map((message) => (
                            <div key={message.id} className={`flex items-start space-x-3 ${message.isBot ? '' : 'flex-row-reverse space-x-reverse'}`}>
                                {message.isBot && (
                                    <div
                                        className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                        style={{ backgroundColor: primaryColor }}
                                    >
                                        {renderBotIcon('sm')}
                                    </div>
                                )}
                                {!message.isBot && (
                                    <div className="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span className="text-white text-xs">👤</span>
                                    </div>
                                )}

                                <div className={`max-w-xs ${message.isBot ? '' : 'text-right'}`}>
                                    <div className={`rounded-2xl px-4 py-3 ${
                                        message.isBot
                                            ? 'bg-gray-100'
                                            : 'text-white'
                                    }`}
                                    style={message.isBot ?
                                        { color: textColor } :
                                        { backgroundColor: primaryColor }
                                    }>
                                        <p className="text-sm leading-relaxed">{message.content}</p>

                                        {message.sources && message.sources.length > 0 && (
                                            <div className="mt-3 pt-3 border-t border-gray-200">
                                                <p className="text-xs font-medium text-gray-500 mb-2">Sources:</p>
                                                <div className="space-y-1">
                                                    {message.sources.map((source, index) => (
                                                        <div key={index} className="text-xs text-gray-600 bg-white rounded px-2 py-1">
                                                            <span className="font-medium">{source.title}</span>
                                                            {source.url && (
                                                                <span className="text-gray-500"> • {source.type}</span>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}

                        {isLoading && (
                            <div className="flex items-start space-x-3">
                                <div
                                    className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    {renderBotIcon('sm')}
                                </div>
                                <div className="bg-gray-100 rounded-2xl px-4 py-3">
                                    <div className="flex space-x-1">
                                        <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                        <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }}></div>
                                        <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></div>
                                    </div>
                                </div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                            </>
                        )}
                    </div>

                    {/* Input Area */}
                    <div className="p-4 border-t border-gray-200">
                        <form onSubmit={sendMessage} className="flex items-center space-x-3">
                            <div className="flex-1">
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    placeholder="Do you have question?"
                                    className="w-full px-4 py-3 bg-gray-50 border-0 rounded-full focus:ring-2 focus:bg-white transition-colors text-sm"
                                    style={{
                                        focusRingColor: primaryColor,
                                        '--tw-ring-color': primaryColor
                                    }}
                                    disabled={isLoading}
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={isLoading || !input.trim()}
                                className="w-12 h-12 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-105 transition-transform disabled:opacity-50 disabled:cursor-not-allowed"
                                style={{ backgroundColor: primaryColor }}
                            >
                                <PaperAirplaneIcon className="h-5 w-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </>
        );
    }

    // Standalone view - original floating widget design
    return (
        <>
            <Head title={`Chat with ${chatbot.name}`} />

            <div className="fixed inset-0 bg-gradient-to-br from-purple-50 to-pink-50 flex items-center justify-center p-4">
                {/* Chat Widget (Floating Button) - Hidden when chat is open */}
                {!isOpen && (
                    <button
                        onClick={() => setIsOpen(true)}
                        className="fixed bottom-6 right-6 w-16 h-16 rounded-full shadow-2xl flex items-center justify-center text-white font-semibold hover:scale-110 transition-all duration-300 z-50"
                        style={{
                            backgroundColor: primaryColor,
                            borderRadius: `${borderRadius}px`
                        }}
                    >
                        <ChatBubbleLeftRightIcon className="w-8 h-8" />
                    </button>
                )}

                {/* Chat Interface */}
                {isOpen && (
                    <div
                        className="fixed bottom-6 right-6 w-96 h-[600px] shadow-2xl overflow-hidden z-50 flex flex-col"
                        style={{
                            backgroundColor: backgroundColor,
                            borderRadius: `${borderRadius}px`
                        }}
                    >
                        {/* Header */}
                        <div
                            className="p-4 text-white flex items-center justify-between"
                            style={{ backgroundColor: primaryColor }}
                        >
                            <div className="flex items-center space-x-3">
                                <div className="w-10 h-10 bg-black bg-opacity-20 rounded-full flex items-center justify-center">
                                    {renderBotIcon('lg')}
                                </div>
                                <div>
                                    <h3 className="font-semibold text-sm">
                                        {chatbot.appearance?.header_text || chatbot.name}
                                    </h3>
                                    <p className="text-xs opacity-90">
                                        {chatbot.appearance?.header_subtitle || 'AI Assistant'}
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => setIsOpen(false)}
                                className="w-8 h-8 rounded-full bg-white bg-opacity-20 flex items-center justify-center hover:bg-opacity-30 transition-colors"
                            >
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-4">
                            {messages.map((message) => (
                                <div key={message.id} className={`flex items-start space-x-3 ${message.isBot ? '' : 'flex-row-reverse space-x-reverse'}`}>
                                    {message.isBot && (
                                        <div
                                            className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            {renderBotIcon('sm')}
                                        </div>
                                    )}
                                    {!message.isBot && (
                                        <div className="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span className="text-white text-xs">👤</span>
                                        </div>
                                    )}

                                    <div className={`max-w-xs ${message.isBot ? '' : 'text-right'}`}>
                                        <div className={`rounded-2xl px-4 py-3 ${
                                            message.isBot
                                                ? 'bg-gray-100'
                                                : 'text-white'
                                        }`}
                                        style={message.isBot ?
                                            { color: textColor } :
                                            { backgroundColor: primaryColor }
                                        }>
                                            <p className="text-sm leading-relaxed">{message.content}</p>

                                            {message.sources && message.sources.length > 0 && (
                                                <div className="mt-3 pt-3 border-t border-gray-200">
                                                    <p className="text-xs font-medium text-gray-500 mb-2">Sources:</p>
                                                    <div className="space-y-1">
                                                        {message.sources.map((source, index) => (
                                                            <div key={index} className="text-xs text-gray-600 bg-white rounded px-2 py-1">
                                                                <span className="font-medium">{source.title}</span>
                                                                {source.url && (
                                                                    <span className="text-gray-500"> • {source.type}</span>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}

                            {isLoading && (
                                <div className="flex items-start space-x-3">
                                    <div
                                        className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                        style={{ backgroundColor: primaryColor }}
                                    >
                                        {renderBotIcon('sm')}
                                    </div>
                                    <div className="bg-gray-100 rounded-2xl px-4 py-3">
                                        <div className="flex space-x-1">
                                            <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                            <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }}></div>
                                            <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></div>
                                        </div>
                                    </div>
                                </div>
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input Area */}
                        <div className="p-4 border-t border-gray-200">
                            <form onSubmit={sendMessage} className="flex items-center space-x-3">
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        placeholder="Do you have question?"
                                        className="w-full px-4 py-3 bg-gray-50 border-0 rounded-full focus:ring-2 focus:bg-white transition-colors text-sm"
                                        style={{
                                            focusRingColor: primaryColor,
                                            '--tw-ring-color': primaryColor
                                        }}
                                        disabled={isLoading}
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={isLoading || !input.trim()}
                                    className="w-12 h-12 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-105 transition-transform disabled:opacity-50 disabled:cursor-not-allowed"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    <PaperAirplaneIcon className="h-5 w-5" />
                                </button>
                            </form>
                        </div>
                    </div>
                )}

            </div>
        </>
    );
}
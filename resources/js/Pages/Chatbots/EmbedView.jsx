import React, { useState, useRef, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { PaperAirplaneIcon, XMarkIcon, ChatBubbleLeftRightIcon, ClipboardDocumentIcon } from '@heroicons/react/24/outline';
import { ClipboardDocumentCheckIcon } from '@heroicons/react/24/solid';
import ReactMarkdown from 'react-markdown';

export default function EmbedView({ chatbot, tenant_id }) {
    // Session management
    const SESSION_EXPIRY_DAYS = 7;
    const SESSION_KEY = `ai_chatbot_embed_session_${chatbot.id}`;
    
    const [sessionId, setSessionId] = useState(null);
    const [isLoadingHistory, setIsLoadingHistory] = useState(true);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(true); // Always open when embedded
    const [copiedCode, setCopiedCode] = useState(null);
    const messagesEndRef = useRef(null);

    const [showMenu, setShowMenu] = useState(false);
    const [queryMode, setQueryMode] = useState('general'); // 'general' or 'sql'
    const [selectedSqlType, setSelectedSqlType] = useState(null); // for SQL submenu

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

    // Code block component with copy functionality
    const CodeBlock = ({ children, className }) => {
        const isCodeBlock = className?.includes('language-');
        const language = className?.replace('language-', '') || '';

        if (isCodeBlock) {
            const codeContent = String(children).replace(/\n$/, '');
            const codeId = `code-${Date.now()}-${Math.random()}`;

            const copyToClipboard = async () => {
                try {
                    await navigator.clipboard.writeText(codeContent);
                    setCopiedCode(codeId);
                    setTimeout(() => setCopiedCode(null), 2000);
                } catch (err) {
                    console.error('Failed to copy text: ', err);
                }
            };

            return (
                <div className="relative group my-4">
                    <div className="flex items-center justify-between bg-gray-800 text-gray-200 px-4 py-2 rounded-t-lg text-sm">
                        <span className="font-medium">{language || 'Code'}</span>
                        <button
                            onClick={copyToClipboard}
                            className="flex items-center space-x-1 text-gray-400 hover:text-white transition-colors"
                        >
                            {copiedCode === codeId ? (
                                <>
                                    <ClipboardDocumentCheckIcon className="h-4 w-4" />
                                    <span className="text-xs">Copied!</span>
                                </>
                            ) : (
                                <>
                                    <ClipboardDocumentIcon className="h-4 w-4" />
                                    <span className="text-xs">Copy</span>
                                </>
                            )}
                        </button>
                    </div>
                    <pre className="bg-gray-900 text-gray-100 p-4 rounded-b-lg overflow-x-auto">
                        <code className={className}>{children}</code>
                    </pre>
                </div>
            );
        }

        return <code className="bg-gray-100 text-gray-800 px-1 py-0.5 rounded text-sm font-mono">{children}</code>;
    };

    // Enhanced contact link component
    const ContactLink = ({ children }) => {
        const extractText = (children) => {
            if (typeof children === 'string') return children;
            if (Array.isArray(children)) {
                return children.map(child => extractText(child)).join('');
            }
            if (children && typeof children === 'object') {
                if (children.type === 'strong' || children.type === 'em' || children.type === 'span') {
                    return extractText(children.props.children);
                }
                if (children.props && children.props.children) {
                    return extractText(children.props.children);
                }
            }
            return String(children || '');
        };

        const text = extractText(children);

        const parseMultipleContacts = (text) => {
            if (text.includes('•') || text.includes('\n') || text.includes('WhatsApp:') || text.includes('Phone:') || text.includes('Email:')) {
                let processedText = text
                    .replace(/Support\s*[\n•]\s*Email:/gi, 'Support Email:')
                    .replace(/Support\s+Email:/gi, 'Support Email:');

                let initialLines = processedText.split(/[\n•]+/).filter(line => line.trim());

                const finalLines = [];
                initialLines.forEach(line => {
                    const trimmed = line.trim();
                    if (!trimmed) return;

                    const parts = trimmed.split(/(?=\s*(?:WhatsApp:|Phone:|Email:|Support\s*Email:|Support\s*Portal:))/);
                    parts.forEach(part => {
                        if (part.trim()) {
                            finalLines.push(part.trim());
                        }
                    });
                });

                const lines = finalLines;
                return lines.map((line, index) => {
                    const trimmedLine = line.trim();
                    if (!trimmedLine) return null;

                    return (
                        <div key={index} className="mb-1 flex items-start">
                            <span className="mr-2 text-gray-600">•</span>
                            <span className="flex-1">{parseLineForContacts(trimmedLine, index)}</span>
                        </div>
                    );
                }).filter(Boolean);
            }

            return [parseLineForContacts(text, 0)];
        };

        const parseLineForContacts = (text, baseKey) => {
            const elements = [];
            let currentIndex = 0;

            const patterns = [
                { regex: /https?:\/\/[^\s]+/g, type: 'url' },
                { regex: /[\w\.-]+@[\w\.-]+\.\w+/g, type: 'email' },
                { regex: /\+[\d\s\-\(\)]{8,}/g, type: 'phone' },
                { regex: /\b[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?/g, type: 'domain' }
            ];

            const matches = [];

            patterns.forEach(pattern => {
                let match;
                const regex = new RegExp(pattern.regex.source, pattern.regex.flags);
                while ((match = regex.exec(text)) !== null) {
                    const newMatch = {
                        type: pattern.type,
                        text: match[0],
                        start: match.index,
                        end: match.index + match[0].length
                    };

                    const hasOverlap = matches.some(existingMatch =>
                        (newMatch.start >= existingMatch.start && newMatch.start < existingMatch.end) ||
                        (newMatch.end > existingMatch.start && newMatch.end <= existingMatch.end) ||
                        (newMatch.start <= existingMatch.start && newMatch.end >= existingMatch.end)
                    );

                    if (!hasOverlap) {
                        matches.push(newMatch);
                    }
                }
            });

            matches.sort((a, b) => a.start - b.start);

            matches.forEach((match, index) => {
                if (match.start > currentIndex) {
                    elements.push(text.substring(currentIndex, match.start));
                }

                let href = '';
                let className = 'text-blue-600 hover:text-blue-800 underline font-medium';
                let target = undefined;
                let rel = undefined;

                switch (match.type) {
                    case 'phone':
                        const isWhatsApp = text.toLowerCase().substring(0, match.start).includes('whatsapp');
                        href = isWhatsApp ? `https://wa.me/${match.text.replace(/[\s\-\(\)]/g, '')}` : `tel:${match.text}`;
                        if (isWhatsApp) {
                            className = 'text-green-600 hover:text-green-800 underline font-medium';
                            target = '_blank';
                            rel = 'noopener noreferrer';
                        }
                        break;
                    case 'email':
                        href = `mailto:${match.text}`;
                        break;
                    case 'url':
                        href = match.text;
                        target = '_blank';
                        rel = 'noopener noreferrer';
                        break;
                    case 'domain':
                        href = `https://${match.text}`;
                        target = '_blank';
                        rel = 'noopener noreferrer';
                        break;
                }

                elements.push(
                    <a
                        key={`link-${baseKey}-${index}`}
                        href={href}
                        target={target}
                        rel={rel}
                        className={className}
                    >
                        {match.text}
                    </a>
                );

                currentIndex = match.end;
            });

            if (currentIndex < text.length) {
                elements.push(text.substring(currentIndex));
            }

            return elements.length > 1 ? elements : text;
        };

        const hasContactInfo = /https?:\/\/[^\s]+|\+[\d\s\-\(\)]{8,}|[\w\.-]+@[\w\.-]+\.\w+|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?/i.test(text);

        if (hasContactInfo) {
            const elements = parseMultipleContacts(text);
            return <span>{elements}</span>;
        }

        return <span>{children}</span>;
    };

    const handleMenuClick = (mode, type = null) => {
        if (mode === 'general') {
            setQueryMode(mode);
            setSelectedSqlType(type);
            setShowMenu(false);
        } else if (mode === 'sql' && !type) {
            setQueryMode(mode);
            setSelectedSqlType(type);
        } else if (mode === 'sql' && type) {
            setSelectedSqlType(type);
            setShowMenu(false);
        }
        
        // Set appropriate placeholder based on selection
        if (mode === 'sql' && type) {
            const placeholders = {
                'invoice': 'Enter email to view invoices',
                'user': 'Enter email to view user information',
                'order': 'Enter email to view orders',
                'account': 'Enter email to view account details',
                'support': 'Enter email to view support tickets',
                'payment': 'Enter email or invoice number to view payments',
                'subscription': 'Enter email to view subscription status',
                'renewal': 'Enter email to view upcoming renewals',
                'stock-status': 'Enter product name to check stock',
                'other': 'Enter your database query'
            };
            // You could set a temporary placeholder or guide message
        }
    };

    const clearHistory = () => {
        if (confirm('Are you sure you want to clear the conversation history? This cannot be undone.')) {
            // Clear localStorage session
            localStorage.removeItem(SESSION_KEY);
            
            // Create new session
            const newSessionId = createNewSession();
            setSessionId(newSessionId);
            
            // Reset messages with welcome message
            setMessages([{
                id: 1,
                content: chatbot.welcome_message || "Hello! How can I help you today?",
                isBot: true,
                timestamp: new Date(),
                sources: []
            }]);
            
            console.log('Conversation history cleared, new session created:', newSessionId);
        }
    };

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    // Update input placeholder based on mode
    const getPlaceholder = () => {
        if (queryMode === 'sql') {
            switch (selectedSqlType) {
                case 'invoice': return 'Enter email to view invoices...';
                case 'user': return 'Enter email to view user information...';
                case 'order': return 'Enter email to view orders...';
                case 'account': return 'Enter email to view account details...';
                case 'support': return 'Enter email to view support tickets...';
                case 'payment': return 'Enter email or invoice number to view payments...';
                case 'subscription': return 'Enter email to view subscription status...';
                case 'renewal': return 'Enter email to view upcoming renewals...';
                case 'stock-status': return 'Enter product name to check stock...';
                case 'other': return 'Enter your database query...';
                default: return 'Choose a query type from the menu...';
            }
        }
        return 'Ask me anything...';
    };

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
                    tenant_id: tenant_id, // Add tenant ID for database isolation
                    query_mode: queryMode, // Add query mode
                    sql_type: selectedSqlType, // Add SQL type if applicable
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
                        {/* Clear History Button */}
                        <button
                            onClick={clearHistory}
                            className="px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 transition-colors flex items-center space-x-2 text-white"
                            title="Clear conversation history"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span className="text-xs font-medium">Clear</span>
                        </button>
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
                                        {message.isBot ? (
                                            <div className="text-sm leading-relaxed">
                                                <ReactMarkdown
                                                    components={{
                                                        p: ({ children }) => {
                                                            const text = String(children);
                                                            const hasContactInfo = /WhatsApp|Phone|Email|Support|\+[\d\s\-\(\)]{8,}|[\w\.-]+@[\w\.-]+\.\w+|https?:\/\/|📱|📞|✉️|🛠️|🌐|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i.test(text);

                                                            if (hasContactInfo) {
                                                                if (text.includes('•') || text.includes('WhatsApp:') || text.includes('Phone:') || text.includes('Email:')) {
                                                                    return (
                                                                        <div className="mb-3 last:mb-0 leading-relaxed space-y-2">
                                                                            <ContactLink>{children}</ContactLink>
                                                                        </div>
                                                                    );
                                                                }
                                                                return (
                                                                    <p className="mb-3 last:mb-0 leading-relaxed">
                                                                        <ContactLink>{children}</ContactLink>
                                                                    </p>
                                                                );
                                                            }
                                                            return <p className="mb-3 last:mb-0 leading-relaxed">{children}</p>;
                                                        },
                                                        strong: ({ children }) => <strong className="font-bold text-gray-900">{children}</strong>,
                                                        em: ({ children }) => <em className="italic text-gray-800">{children}</em>,
                                                        ul: ({ children }) => <ul className="list-disc list-outside ml-4 mb-3 space-y-1">{children}</ul>,
                                                        ol: ({ children }) => <ol className="list-decimal list-outside ml-4 mb-3 space-y-1">{children}</ol>,
                                                        li: ({ children }) => <li className="text-gray-800 leading-relaxed pl-1">{children}</li>,
                                                        h1: ({ children }) => <h1 className="text-lg font-bold mb-3 mt-2 text-gray-900">{children}</h1>,
                                                        h2: ({ children }) => <h2 className="text-base font-bold mb-2 mt-2 text-gray-900">{children}</h2>,
                                                        h3: ({ children }) => <h3 className="text-sm font-bold mb-2 mt-1 text-gray-900">{children}</h3>,
                                                        h4: ({ children }) => <h4 className="text-sm font-semibold mb-1 text-gray-800">{children}</h4>,
                                                        blockquote: ({ children }) => (
                                                            <blockquote className="border-l-4 border-gray-300 pl-4 my-3 italic text-gray-700">
                                                                {children}
                                                            </blockquote>
                                                        ),
                                                        code: ({ children, inline, className }) =>
                                                            inline ? (
                                                                <code className="bg-gray-100 px-1 py-0.5 rounded text-sm font-mono text-gray-800">
                                                                    {children}
                                                                </code>
                                                            ) : (
                                                                <CodeBlock className={className}>{children}</CodeBlock>
                                                            ),
                                                        pre: ({ children }) => <pre className="mb-3">{children}</pre>,
                                                        hr: () => <hr className="my-4 border-gray-300" />,
                                                        a: ({ children, href }) => <ContactLink href={href}>{children}</ContactLink>,
                                                    }}
                                                >
                                                    {message.content}
                                                </ReactMarkdown>
                                            </div>
                                        ) : (
                                            <div className="text-sm leading-relaxed whitespace-pre-line">{message.content}</div>
                                        )}

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
                    <div className="p-4 border-t border-gray-200 relative">
                        {showMenu && (
                            <div className="absolute bottom-full left-0 mb-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-50 overflow-y-auto">
                                <div className="p-2">
                                    {queryMode === 'general' && (
                                        <>
                                            <button
                                                onClick={() => handleMenuClick('general')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    queryMode === 'general' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700'
                                                }`}
                                            >
                                                🤖 General
                                                {/* <span className="block text-xs text-gray-500 mt-1">AI-powered answers from knowledge base</span> */}
                                            </button>
                                            
                                            <button
                                                onClick={() => handleMenuClick('sql')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors mt-1 ${
                                                    queryMode === 'sql' ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-700'
                                                }`}
                                            >
                                                🗄️ SQL
                                                {/* <span className="block text-xs text-gray-500 mt-1">Direct database queries</span> */}
                                            </button>
                                        </>    
                                    )}
                                    
                                    {queryMode === 'sql' && (
                                        <div className="mt-2 ml-4 space-y-1">
                                            <button
                                                onClick={() => { setQueryMode('general'); setSelectedSqlType(null); }}
                                                className="w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors text-gray-700"
                                            >
                                                ← Back
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'invoice')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'invoice' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                📄 Invoices
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'order')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'order' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                🛒 Orders
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'payment')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'payment' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                💳 Payments
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'account')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'account' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                👤 Account
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'subscription')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'subscription' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                📅 Subscriptions
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'renewal')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'renewal' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                🔔 Renewals
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'support')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'support' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                🎫 Support Tickets
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'stock-status')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'stock-status' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                📊 Stock Status
                                            </button>
                                            <button
                                                onClick={() => handleMenuClick('sql', 'user')}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm hover:bg-gray-50 transition-colors ${
                                                    selectedSqlType === 'user' ? 'bg-green-100 text-green-800' : 'text-gray-600'
                                                }`}
                                            >
                                                🔍 User Search
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                        <form onSubmit={sendMessage} className="flex items-center space-x-3">
                            {/* Add hamburger menu button */}
                            <button
                                type="button"
                                onClick={() => setShowMenu(!showMenu)}
                                className="w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-100 transition-colors"
                                style={{ color: primaryColor }}
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div className="flex-1">
                                <input
                                    type="text"
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    // placeholder="Do you have question?"
                                    placeholder={getPlaceholder()}
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
                                            {message.isBot ? (
                                                <div className="text-sm leading-relaxed">
                                                    <ReactMarkdown
                                                        components={{
                                                            p: ({ children }) => {
                                                                const text = String(children);
                                                                const hasContactInfo = /WhatsApp|Phone|Email|Support|\+[\d\s\-\(\)]{8,}|[\w\.-]+@[\w\.-]+\.\w+|https?:\/\/|📱|📞|✉️|🛠️|🌐|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i.test(text);

                                                                if (hasContactInfo) {
                                                                    if (text.includes('•') || text.includes('WhatsApp:') || text.includes('Phone:') || text.includes('Email:')) {
                                                                        return (
                                                                            <div className="mb-3 last:mb-0 leading-relaxed space-y-2">
                                                                                <ContactLink>{children}</ContactLink>
                                                                            </div>
                                                                        );
                                                                    }
                                                                    return (
                                                                        <p className="mb-3 last:mb-0 leading-relaxed">
                                                                            <ContactLink>{children}</ContactLink>
                                                                        </p>
                                                                    );
                                                                }
                                                                return <p className="mb-3 last:mb-0 leading-relaxed">{children}</p>;
                                                            },
                                                            strong: ({ children }) => <strong className="font-bold text-gray-900">{children}</strong>,
                                                            em: ({ children }) => <em className="italic text-gray-800">{children}</em>,
                                                            ul: ({ children }) => <ul className="list-disc list-outside ml-4 mb-3 space-y-1">{children}</ul>,
                                                            ol: ({ children }) => <ol className="list-decimal list-outside ml-4 mb-3 space-y-1">{children}</ol>,
                                                            li: ({ children }) => <li className="text-gray-800 leading-relaxed pl-1">{children}</li>,
                                                            h1: ({ children }) => <h1 className="text-lg font-bold mb-3 mt-2 text-gray-900">{children}</h1>,
                                                            h2: ({ children }) => <h2 className="text-base font-bold mb-2 mt-2 text-gray-900">{children}</h2>,
                                                            h3: ({ children }) => <h3 className="text-sm font-bold mb-2 mt-1 text-gray-900">{children}</h3>,
                                                            h4: ({ children }) => <h4 className="text-sm font-semibold mb-1 text-gray-800">{children}</h4>,
                                                            blockquote: ({ children }) => (
                                                                <blockquote className="border-l-4 border-gray-300 pl-4 my-3 italic text-gray-700">
                                                                    {children}
                                                                </blockquote>
                                                            ),
                                                            code: ({ children, inline, className }) =>
                                                                inline ? (
                                                                    <code className="bg-gray-100 px-1 py-0.5 rounded text-sm font-mono text-gray-800">
                                                                        {children}
                                                                    </code>
                                                                ) : (
                                                                    <CodeBlock className={className}>{children}</CodeBlock>
                                                                ),
                                                            pre: ({ children }) => <pre className="mb-3">{children}</pre>,
                                                            hr: () => <hr className="my-4 border-gray-300" />,
                                                            a: ({ children, href }) => <ContactLink href={href}>{children}</ContactLink>,
                                                        }}
                                                    >
                                                        {message.content}
                                                    </ReactMarkdown>
                                                </div>
                                            ) : (
                                                <div className="text-sm leading-relaxed whitespace-pre-line">{message.content}</div>
                                            )}

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
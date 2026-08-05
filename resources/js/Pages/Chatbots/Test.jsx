import React, { useState, useRef, useEffect } from 'react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import ContactInfo from '../../Components/ContactInfo';
import { PaperAirplaneIcon, HandThumbUpIcon, HandThumbDownIcon, ClipboardDocumentIcon } from '@heroicons/react/24/outline';
import { HandThumbUpIcon as HandThumbUpSolidIcon, HandThumbDownIcon as HandThumbDownSolidIcon, ClipboardDocumentCheckIcon } from '@heroicons/react/24/solid';
import ReactMarkdown from 'react-markdown';
import { useEcho } from '../../contexts/EchoContext';

export default function TestChatbot({ chatbot }) {
    // Session management
    const SESSION_EXPIRY_DAYS = 7;
    const SESSION_KEY = `ai_chatbot_session_${chatbot.id}`;
    
    const [sessionId, setSessionId] = useState(null);
    const [isLoadingHistory, setIsLoadingHistory] = useState(true);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [feedbackInput, setFeedbackInput] = useState('');
    const [showFeedbackForm, setShowFeedbackForm] = useState(null);
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [showNetworkWarning, setShowNetworkWarning] = useState(false);
    const [retryTimeout, setRetryTimeout] = useState(null);
    const messagesEndRef = useRef(null);
    const textareaRef = useRef(null);
    const { echo, isConnected } = useEcho();
    const [copiedCode, setCopiedCode] = useState(null);

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
                    console.log('Existing session found:', currentSessionId);
                    
                    // Load previous messages
                    await loadConversationHistory(currentSessionId);
                } else {
                    console.log('Session expired, creating new session');
                    currentSessionId = createNewSession();
                    setMessages([{
                        id: 1,
                        content: chatbot.welcome_message,
                        isBot: true,
                        timestamp: new Date(),
                        sources: [],
                        learningDataId: null,
                        feedback: null
                    }]);
                }
            } else {
                console.log('No session found, creating new session');
                currentSessionId = createNewSession();
                setMessages([{
                    id: 1,
                    content: chatbot.welcome_message,
                    isBot: true,
                    timestamp: new Date(),
                    sources: [],
                    learningDataId: null,
                    feedback: null
                }]);
            }

            setSessionId(currentSessionId);
        } catch (error) {
            console.error('Failed to initialize session:', error);
            const newSessionId = createNewSession();
            setSessionId(newSessionId);
            setMessages([{
                id: 1,
                content: chatbot.welcome_message,
                isBot: true,
                timestamp: new Date(),
                sources: [],
                learningDataId: null,
                feedback: null
            }]);
        } finally {
            setIsLoadingHistory(false);
        }
    };

    const createNewSession = () => {
        const newSessionId = 'test_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
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
                console.log('Loaded', data.messages.length, 'previous messages');
                const history = data.messages.map(msg => ({
                    id: msg.id,
                    content: msg.content,
                    // API sends `isBot`; fall back to the raw column name just in case
                    isBot: Boolean(msg.isBot ?? msg.is_bot),
                    timestamp: new Date(msg.timestamp ?? msg.created_at),
                    sources: msg.sources || [],
                    learningDataId: msg.learningDataId ?? null,
                    feedback: msg.feedback ?? null,
                    showContactInfo: msg.showContactInfo ?? false
                }));

                // The welcome message is never persisted, so re-add it on top
                setMessages([{
                    id: 'welcome',
                    content: chatbot.welcome_message,
                    isBot: true,
                    timestamp: history[0].timestamp,
                    sources: [],
                    learningDataId: null,
                    feedback: null
                }, ...history]);
            } else {
                console.log('No previous messages found, starting fresh');
                setMessages([{
                    id: 1,
                    content: chatbot.welcome_message,
                    isBot: true,
                    timestamp: new Date(),
                    sources: [],
                    learningDataId: null,
                    feedback: null
                }]);
            }
        } catch (error) {
            console.error('Failed to load conversation history:', error);
            setMessages([{
                id: 1,
                content: chatbot.welcome_message,
                isBot: true,
                timestamp: new Date(),
                sources: [],
                learningDataId: null,
                feedback: null
            }]);
        }
    };

    const clearHistory = () => {
        if (confirm('Are you sure you want to clear the conversation history? This will start a fresh conversation.')) {
            // Remove session from localStorage
            localStorage.removeItem(SESSION_KEY);
            
            // Create new session
            const newSessionId = createNewSession();
            setSessionId(newSessionId);
            
            // Reset messages to welcome message
            setMessages([{
                id: 1,
                content: chatbot.welcome_message,
                isBot: true,
                timestamp: new Date(),
                sources: [],
                learningDataId: null,
                feedback: null
            }]);
            
            console.log('Conversation history cleared, new session:', newSessionId);
        }
    };

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

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

        if (chatbot.appearance?.icon_type === 'emoji' && chatbot.appearance?.selected_emoji) {
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

    // Enhanced contact link component that handles multiple contact types
    const ContactLink = ({ children }) => {
        // Extract text from React children, handling markdown elements properly
        const extractText = (children) => {
            if (typeof children === 'string') {
                return children;
            }
            if (Array.isArray(children)) {
                return children.map(child => extractText(child)).join('');
            }
            if (children && typeof children === 'object') {
                // Handle React elements (like <strong>, <em>, etc.)
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

        // Parse text and create elements with multiple contact links
        const parseMultipleContacts = (text) => {
            // If text contains bullet points or line breaks, format as list
            if (text.includes('•') || text.includes('\n') || text.includes('WhatsApp:') || text.includes('Phone:') || text.includes('Email:')) {
                // Pre-process text to fix "Support" and "Email:" separation
                let processedText = text
                    .replace(/Support\s*[\n•]\s*Email:/gi, 'Support Email:')
                    .replace(/Support\s+Email:/gi, 'Support Email:');

                // Split by bullet points and line breaks, but be smarter about multi-word labels
                let initialLines = processedText.split(/[\n•]+/).filter(line => line.trim());

                // Additional splitting for contact labels that might be together
                const finalLines = [];
                initialLines.forEach(line => {
                    const trimmed = line.trim();
                    if (!trimmed) return;

                    // Split by contact patterns but preserve multi-word labels
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

            // Single line processing
            return [parseLineForContacts(text, 0)];
        };

        const parseLineForContacts = (text, baseKey) => {
            const elements = [];
            let currentIndex = 0;

            // Find all contact patterns with their positions - dynamic patterns
            const patterns = [
                { regex: /https?:\/\/[^\s]+/g, type: 'url' }, // Any HTTP/HTTPS URL (check first)
                { regex: /[\w\.-]+@[\w\.-]+\.\w+/g, type: 'email' }, // Any email format (check before domain to prevent overlap)
                { regex: /\+[\d\s\-\(\)]{8,}/g, type: 'phone' }, // Any international phone format
                { regex: /\b[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?/g, type: 'domain' } // Any domain with optional path
            ];

            const matches = [];

            // Collect all matches with their positions
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

                    // Check if this match overlaps with any existing match
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

            // Sort matches by position
            matches.sort((a, b) => a.start - b.start);

            // Process matches and create elements
            matches.forEach((match, index) => {
                // Add text before this match
                if (match.start > currentIndex) {
                    elements.push(text.substring(currentIndex, match.start));
                }

                // Create link element based on type
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

            // Add remaining text
            if (currentIndex < text.length) {
                elements.push(text.substring(currentIndex));
            }

            return elements.length > 1 ? elements : text;
        };

        // Check if this text contains contact information - dynamic detection
        const hasContactInfo = /https?:\/\/[^\s]+|\+[\d\s\-\(\)]{8,}|[\w\.-]+@[\w\.-]+\.\w+|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?/i.test(text);

        if (hasContactInfo) {
            const elements = parseMultipleContacts(text);
            return <span>{elements}</span>;
        }

        return <span>{children}</span>;
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    // Network monitoring
    useEffect(() => {
        const handleOnline = () => {
            setIsOnline(true);
            setShowNetworkWarning(false);
            if (retryTimeout) {
                clearTimeout(retryTimeout);
                setRetryTimeout(null);
            }
        };

        const handleOffline = () => {
            setIsOnline(false);
            setShowNetworkWarning(true);

            // Start retry mechanism
            const timeout = setTimeout(() => {
                if (!navigator.onLine) {
                    setShowNetworkWarning(true);
                    // Retry every 10 seconds
                    const retryInterval = setInterval(() => {
                        if (navigator.onLine) {
                            clearInterval(retryInterval);
                            setIsOnline(true);
                            setShowNetworkWarning(false);
                        }
                    }, 10000);

                    setRetryTimeout(retryInterval);
                }
            }, 1000);

            setRetryTimeout(timeout);
        };

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
            if (retryTimeout) {
                clearTimeout(retryTimeout);
            }
        };
    }, [retryTimeout]);

    const sendMessage = async (e) => {
        e.preventDefault();
        if (!input.trim() || isLoading || !sessionId) return;

        // Check network status
        if (!isOnline) {
            setShowNetworkWarning(true);
            return;
        }

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

            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                console.error('Failed to parse response JSON:', parseError);
                throw new Error('Invalid response format from server');
            }

            if (response.ok) {
                const botMessage = {
                    id: Date.now() + 1,
                    content: data.reply || 'Sorry, I received an empty response.',
                    isBot: true,
                    timestamp: new Date(),
                    sources: data.sources || [],
                    learningDataId: data.learning_data_id || null,
                    feedback: null,
                    showContactInfo: data.show_contact_info || false
                };
                setMessages(prev => [...prev, botMessage]);
            } else {
                console.error('API Error Response:', response.status, data);
                const errorMessage = {
                    id: Date.now() + 1,
                    content: data.error || data.message || `Server error (${response.status}). Please try again.`,
                    isBot: true,
                    timestamp: new Date(),
                    sources: []
                };
                setMessages(prev => [...prev, errorMessage]);
            }
        } catch (error) {
            console.error('Chat API Error:', error);

            // Network error detection
            setIsOnline(false);
            setShowNetworkWarning(true);

            const errorMessage = {
                id: Date.now() + 1,
                content: `Sorry, I encountered an error: ${error.message || 'Network error'}. Please check your connection and try again.`,
                isBot: true,
                timestamp: new Date(),
                sources: []
            };
            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
            // Maintain focus on textarea after sending message
            setTimeout(() => {
                if (textareaRef.current) {
                    textareaRef.current.focus();
                }
            }, 100);
        }
    };

    const handleFeedback = async (messageId, wasHelpful) => {
        const message = messages.find(m => m.id === messageId);
        if (!message || !message.learningDataId) return;

        try {
            await fetch('/api/feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    learning_data_id: message.learningDataId,
                    was_helpful: wasHelpful,
                }),
            });

            setMessages(prev => prev.map(m =>
                m.id === messageId
                    ? { ...m, feedback: wasHelpful ? 'helpful' : 'not_helpful' }
                    : m
            ));
        } catch (error) {
            console.error('Failed to submit feedback:', error);
        }
    };

    const handleCorrection = async (messageId, correction) => {
        const message = messages.find(m => m.id === messageId);
        if (!message || !message.learningDataId) return;

        try {
            await fetch('/api/correction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    learning_data_id: message.learningDataId,
                    correction: correction,
                }),
            });

            setMessages(prev => prev.map(m =>
                m.id === messageId
                    ? { ...m, correctionSubmitted: true }
                    : m
            ));

            setShowFeedbackForm(null);
            setFeedbackInput('');
        } catch (error) {
            console.error('Failed to submit correction:', error);
        }
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Test Assistant - ${chatbot.name}`} enableScroll={false}>
            <div className="flex-1 flex flex-col bg-gradient-to-br from-purple-50 via-white to-pink-50 overflow-hidden">
                {/* Fixed Header */}
                <div className="flex-shrink-0 text-center py-6 px-4 relative">
                    {/* Clear History Button */}
                    
                    {/*print console log here*/}
                    {console.log('Messages length:', messages.length, 'Is loading history:', isLoadingHistory)}
                    
                    {messages.length > 1 && !isLoadingHistory && (
                        <button
                            onClick={clearHistory}
                            className="absolute top-4 right-4 text-xs px-3 py-1.5 bg-red-100 text-red-600 hover:bg-red-200 rounded-lg transition-colors flex items-center space-x-1"
                        >
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Clear History</span>
                        </button>
                    )}
                    
                    <div className="relative inline-block mb-4">
                        <div className="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto shadow-lg">
                            <div
                                className="w-12 h-12 rounded-full flex items-center justify-center"
                                style={{ backgroundColor: chatbot.appearance?.primary_color || '#4F46E5' }}
                            >
                                <div className="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    {renderBotIcon('lg')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <h1 className="text-2xl font-bold text-gray-800 mb-1">
                        Hi there, {chatbot.appearance?.header_text || chatbot.name}
                    </h1>
                    <h2 className="text-lg text-gray-600">
                        How can I help you today?
                    </h2>
                    <div className="mt-2 flex items-center justify-center space-x-4">
                        <p className="text-xs text-gray-500 flex items-center">
                            <span className={`w-2 h-2 rounded-full mr-2 ${isOnline ? 'bg-green-400' : 'bg-red-400'}`}></span>
                            {isOnline ? 'Online' : 'Offline'}
                        </p>
                        {echo && (
                            <p className="text-xs text-gray-500 flex items-center">
                                <span className={`w-2 h-2 rounded-full mr-2 ${isConnected ? 'bg-green-400' : 'bg-red-400'}`}></span>
                                WebSocket {isConnected ? 'Connected' : 'Disconnected'}
                            </p>
                        )}
                    </div>
                </div>

                {/* Network Warning Banner */}
                {showNetworkWarning && (
                    <div className="mx-4 mb-4 bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-lg flex items-center justify-between">
                        <div className="flex items-center">
                            <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                            </svg>
                            <span className="font-medium">No network connection</span>
                            <span className="ml-2 text-sm">Checking connection every 10 seconds...</span>
                        </div>
                        <button
                            onClick={() => setShowNetworkWarning(false)}
                            className="text-red-400 hover:text-red-600"
                        >
                            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                            </svg>
                        </button>
                    </div>
                )}

                {/* Chat Area - Takes remaining space */}
                <div className="flex-1 flex flex-col px-4 pb-4 min-h-0">
                    <div className="flex-1 bg-white rounded-3xl shadow-xl overflow-hidden flex flex-col min-h-0">

                        {/* Messages - Scrollable */}
                        <div className="flex-1 overflow-y-auto p-6 space-y-4 min-h-0">
                            {isLoadingHistory ? (
                                <div className="flex items-center justify-center h-full">
                                    <div className="text-center">
                                        <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mb-2"></div>
                                        <p className="text-gray-500 text-sm">Loading conversation history...</p>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    {messages.map((message) => (
                                <div key={message.id} className={`flex items-start space-x-3 ${message.isBot ? '' : 'flex-row-reverse space-x-reverse'}`}>
                                    {message.isBot && (
                                        <div
                                            className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                            style={{ backgroundColor: chatbot.appearance?.primary_color || '#4F46E5' }}
                                        >
                                            {renderBotIcon('xs')}
                                        </div>
                                    )}
                                    {!message.isBot && (
                                        <div className="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span className="text-white text-xs">You</span>
                                        </div>
                                    )}

                                    <div className={`max-w-md ${message.isBot ? '' : 'text-right'}`}>
                                        <div className="text-xs text-gray-500 mb-1">
                                            {message.isBot ? chatbot.name : 'You'}
                                        </div>
                                        <div className={`rounded-2xl px-4 py-3 ${
                                            message.isBot
                                                ? 'bg-gray-100 text-gray-800'
                                                : 'bg-gray-800 text-white'
                                        }`}>
                                            {message.isBot ? (
                                                <div className="text-sm leading-relaxed">
                                                    <ReactMarkdown
                                                        components={{
                                                            p: ({ children }) => {
                                                                const text = String(children);
                                                                // Use enhanced contact detection
                                                                const hasContactInfo = /WhatsApp|Phone|Email|Support|\+[\d\s\-\(\)]{8,}|[\w\.-]+@[\w\.-]+\.\w+|https?:\/\/|📱|📞|✉️|🛠️|🌐|[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i.test(text);

                                                                if (hasContactInfo) {
                                                                    // If the text contains bullet points, format as list
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
                                                            li: ({ children }) => {
                                                                // Extract text content properly from React children
                                                                const extractTextFromChildren = (children) => {
                                                                    if (typeof children === 'string') return children;
                                                                    if (Array.isArray(children)) {
                                                                        return children.map(child => extractTextFromChildren(child)).join('');
                                                                    }
                                                                    if (children && typeof children === 'object' && children.props) {
                                                                        return extractTextFromChildren(children.props.children);
                                                                    }
                                                                    return '';
                                                                };

                                                                const text = extractTextFromChildren(children);
                                                                const hasContactInfo = /WhatsApp|Phone|Email|Support|\+[\d\s\-\(\)]+|[\w\.-]+@[\w\.-]+\.\w+|https?:\/\/|📱|📞|✉️|🛠️|🌐/i.test(text);

                                                                if (hasContactInfo) {
                                                                    // Parse contact information and make it clickable
                                                                    const renderContactContent = (children) => {
                                                                        const text = extractTextFromChildren(children);

                                                                        // Handle email links
                                                                        if (text.includes('@')) {
                                                                            const emailMatch = text.match(/([\w\.-]+@[\w\.-]+\.\w+)/);
                                                                            if (emailMatch) {
                                                                                const email = emailMatch[1];
                                                                                const beforeEmail = text.substring(0, text.indexOf(email));
                                                                                const afterEmail = text.substring(text.indexOf(email) + email.length);
                                                                                return (
                                                                                    <>
                                                                                        {beforeEmail}
                                                                                        <a href={`mailto:${email}`} className="text-blue-600 hover:text-blue-800 underline">
                                                                                            {email}
                                                                                        </a>
                                                                                        {afterEmail}
                                                                                    </>
                                                                                );
                                                                            }
                                                                        }

                                                                        // Handle phone links
                                                                        if (text.includes('+')) {
                                                                            const phoneMatch = text.match(/(\+[\d\s\-\(\)]{8,})/);
                                                                            if (phoneMatch) {
                                                                                const phone = phoneMatch[1];
                                                                                const beforePhone = text.substring(0, text.indexOf(phone));
                                                                                const afterPhone = text.substring(text.indexOf(phone) + phone.length);
                                                                                return (
                                                                                    <>
                                                                                        {beforePhone}
                                                                                        <a href={`tel:${phone.replace(/\s/g, '')}`} className="text-blue-600 hover:text-blue-800 underline">
                                                                                            {phone}
                                                                                        </a>
                                                                                        {afterPhone}
                                                                                    </>
                                                                                );
                                                                            }
                                                                        }

                                                                        // Handle URL links
                                                                        if (text.includes('http')) {
                                                                            const urlMatch = text.match(/(https?:\/\/[^\s]+)/);
                                                                            if (urlMatch) {
                                                                                const url = urlMatch[1];
                                                                                const beforeUrl = text.substring(0, text.indexOf(url));
                                                                                const afterUrl = text.substring(text.indexOf(url) + url.length);
                                                                                return (
                                                                                    <>
                                                                                        {beforeUrl}
                                                                                        <a href={url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800 underline">
                                                                                            {url}
                                                                                        </a>
                                                                                        {afterUrl}
                                                                                    </>
                                                                                );
                                                                            }
                                                                        }

                                                                        // Return original text if no contact info found
                                                                        return text;
                                                                    };

                                                                    return (
                                                                        <li className="text-gray-800 leading-relaxed pl-1">
                                                                            {renderContactContent(children)}
                                                                        </li>
                                                                    );
                                                                }
                                                                return <li className="text-gray-800 leading-relaxed pl-1">{children}</li>;
                                                            },
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
                                        </div>

                                        {/* Show ContactInfo component for bot messages when needed */}
                                        {message.isBot && message.showContactInfo && (
                                            <div className="mt-3">
                                                <ContactInfo chatbot={chatbot} className="text-sm" />
                                            </div>
                                        )}

                                        {/* Feedback + correction controls: only for bot replies that were recorded for learning */}
                                        {message.isBot && message.learningDataId && (
                                            <div className="mt-2">
                                                <div className="flex items-center space-x-1">
                                                    <button
                                                        type="button"
                                                        title="Helpful"
                                                        onClick={() => handleFeedback(message.id, true)}
                                                        className={`p-1.5 rounded-full transition-colors ${
                                                            message.feedback === 'helpful'
                                                                ? 'text-green-600 bg-green-50'
                                                                : 'text-gray-400 hover:text-green-600 hover:bg-green-50'
                                                        }`}
                                                    >
                                                        {message.feedback === 'helpful' ? (
                                                            <HandThumbUpSolidIcon className="w-4 h-4" />
                                                        ) : (
                                                            <HandThumbUpIcon className="w-4 h-4" />
                                                        )}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        title="Not helpful"
                                                        onClick={() => handleFeedback(message.id, false)}
                                                        className={`p-1.5 rounded-full transition-colors ${
                                                            message.feedback === 'not_helpful'
                                                                ? 'text-red-600 bg-red-50'
                                                                : 'text-gray-400 hover:text-red-600 hover:bg-red-50'
                                                        }`}
                                                    >
                                                        {message.feedback === 'not_helpful' ? (
                                                            <HandThumbDownSolidIcon className="w-4 h-4" />
                                                        ) : (
                                                            <HandThumbDownIcon className="w-4 h-4" />
                                                        )}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setShowFeedbackForm(showFeedbackForm === message.id ? null : message.id);
                                                            setFeedbackInput('');
                                                        }}
                                                        className="ml-1 text-xs text-gray-500 hover:text-indigo-600 hover:underline"
                                                    >
                                                        Suggest a better answer
                                                    </button>

                                                    {message.correctionSubmitted && (
                                                        <span className="ml-2 text-xs text-green-600">
                                                            ✓ Sent for review
                                                        </span>
                                                    )}
                                                </div>

                                                {showFeedbackForm === message.id && (
                                                    <div className="mt-2 p-3 bg-white border border-gray-200 rounded-xl shadow-sm">
                                                        <textarea
                                                            value={feedbackInput}
                                                            onChange={(e) => setFeedbackInput(e.target.value)}
                                                            rows={4}
                                                            maxLength={5000}
                                                            placeholder="Write the answer the assistant should have given..."
                                                            className="w-full text-sm border border-gray-200 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                                        />
                                                        <div className="flex items-center justify-between mt-2">
                                                            <span
                                                                className={`text-xs ${
                                                                    feedbackInput.trim().length > 100
                                                                        ? 'text-green-600'
                                                                        : 'text-gray-400'
                                                                }`}
                                                            >
                                                                {feedbackInput.trim().length} / 100 characters minimum
                                                            </span>
                                                            <div className="space-x-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setShowFeedbackForm(null);
                                                                        setFeedbackInput('');
                                                                    }}
                                                                    className="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800"
                                                                >
                                                                    Cancel
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    disabled={feedbackInput.trim().length <= 100}
                                                                    onClick={() => handleCorrection(message.id, feedbackInput.trim())}
                                                                    className="px-3 py-1.5 text-xs text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                                                >
                                                                    Submit correction
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}

                            {isLoading && (
                                <div className="flex items-start space-x-3">
                                    <div
                                        className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                        style={{ backgroundColor: chatbot.appearance?.primary_color || '#4F46E5' }}
                                    >
                                        {renderBotIcon('xs')}
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

                        {/* Input Area - Fixed at bottom */}
                        <div className="flex-shrink-0 border-t border-gray-200 p-6">
                            <form onSubmit={sendMessage} className="flex items-end space-x-4">
                                <div className="flex-1">
                                    <textarea
                                        ref={textareaRef}
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && !e.shiftKey) {
                                                e.preventDefault();
                                                sendMessage(e);
                                            }
                                        }}
                                        placeholder="Ask me anything... (Shift+Enter for new line)"
                                        className="w-full px-4 py-3 bg-gray-50 border-0 rounded-2xl focus:ring-2 focus:ring-purple-500 focus:bg-white transition-colors text-sm resize-none min-h-[48px] max-h-32"
                                        disabled={isLoading || !isOnline}
                                        rows="1"
                                        style={{
                                            height: 'auto',
                                            minHeight: '48px'
                                        }}
                                        onInput={(e) => {
                                            e.target.style.height = 'auto';
                                            e.target.style.height = Math.min(e.target.scrollHeight, 128) + 'px';
                                        }}
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={isLoading || !input.trim() || !isOnline}
                                    className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white shadow-lg hover:shadow-xl transition-shadow disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <PaperAirplaneIcon className="h-5 w-5" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </ChatbotLayout>
    );
}

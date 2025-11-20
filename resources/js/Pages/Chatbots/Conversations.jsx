import React, { useState, useEffect, useRef, useCallback } from 'react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import { MagnifyingGlassIcon, PhoneIcon, UserIcon, ChevronUpIcon, ChevronDownIcon, Bars3Icon, HandRaisedIcon, XMarkIcon } from '@heroicons/react/24/outline';
import ReactMarkdown from 'react-markdown';
import { toast } from 'react-toastify';

export default function ChatbotConversations({ chatbot, conversations }) {
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
    const [selectedConversation, setSelectedConversation] = useState(conversations.data[0] || null);
    const [searchTerm, setSearchTerm] = useState('');
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [rightSidebarCollapsed, setRightSidebarCollapsed] = useState(true); // Default collapsed
    const [allConversations, setAllConversations] = useState(conversations.data);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [hasMorePages, setHasMorePages] = useState(conversations.next_page_url !== null);
    const [currentPage, setCurrentPage] = useState(1);
    const [newMessage, setNewMessage] = useState('');
    const [isSending, setIsSending] = useState(false);
    const [isProcessingTakeover, setIsProcessingTakeover] = useState(false);
    const [takeoverNote, setTakeoverNote] = useState('');
    const [showTakeoverModal, setShowTakeoverModal] = useState(false);
    const [takeoverAction, setTakeoverAction] = useState(''); // 'takeover' or 'release'
    const [isGeneratingSummary, setIsGeneratingSummary] = useState(false);
    const [showSummaryModal, setShowSummaryModal] = useState(false);
    const scrollRef = useRef(null);
    const loadingRef = useRef(false);

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
                robot: <svg className={`${sizeClasses[size]} text-white`} fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 616 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>,
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

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    };

    const formatTime = (dateString) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    };

    const getInitials = (name) => {
        return name ? name.split(' ').map(n => n[0]).join('').toUpperCase() : 'U';
    };

    const getConversationType = (sessionId) => {
        return sessionId?.startsWith('test_') ? 'Test Chat' : 'Live Chat';
    };

    // Load more conversations on scroll
    const loadMoreConversations = async () => {
        if (loadingRef.current || isLoadingMore || !hasMorePages) return;

        loadingRef.current = true;
        setIsLoadingMore(true);

        try {
            const response = await fetch(`/chatbots/${chatbot.id}/conversations?page=${currentPage + 1}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });
            const data = await response.json();

            if (data.props?.conversations) {
                setAllConversations(prev => [...prev, ...data.props.conversations.data]);
                setCurrentPage(prev => prev + 1);
                setHasMorePages(data.props.conversations.next_page_url !== null);
            }
        } catch (error) {
            console.error('Failed to load more conversations:', error);
        } finally {
            setIsLoadingMore(false);
            // Reset loading flag after a short delay
            setTimeout(() => {
                loadingRef.current = false;
            }, 500);
        }
    };

    // Handle scroll for loading more with throttling
    const handleScroll = useCallback((e) => {
        const { scrollTop, scrollHeight, clientHeight } = e.target;
        if (scrollHeight - scrollTop <= clientHeight + 100 && !loadingRef.current) {
            loadMoreConversations();
        }
    }, [loadMoreConversations]);

    const filteredConversations = allConversations.filter(conv =>
        conv.session_id?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        conv.ip_address?.includes(searchTerm) ||
        conv.messages?.some(msg => msg.content?.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    // Function to send a new message in the conversation
    const sendMessage = async () => {
        if (!newMessage.trim() || !selectedConversation || isSending) return;

        setIsSending(true);
        const messageToSend = newMessage.trim();
        setNewMessage('');

        try {
            const response = await fetch('/api/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                    message: messageToSend,
                    chatbot_id: chatbot.id,
                    session_id: selectedConversation.session_id,
                    conversation_id: selectedConversation.id
                }),
            });

            if (response.ok) {
                const data = await response.json();

                // Update the selected conversation with new messages
                const updatedConversation = {
                    ...selectedConversation,
                    messages: [
                        ...selectedConversation.messages,
                        {
                            id: Date.now() + 1,
                            content: messageToSend,
                            is_bot: false,
                            created_at: new Date().toISOString(),
                            sources: []
                        },
                        {
                            id: Date.now() + 2,
                            content: data.reply,
                            is_bot: true,
                            created_at: new Date().toISOString(),
                            sources: data.sources || []
                        }
                    ]
                };

                setSelectedConversation(updatedConversation);

                // Update the conversation in the list
                setAllConversations(prev =>
                    prev.map(conv =>
                        conv.id === selectedConversation.id ? updatedConversation : conv
                    )
                );
            } else {
                alert('Failed to send message. Please try again.');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('An error occurred. Please try again.');
        } finally {
            setIsSending(false);
        }
    };

    // Handle Enter key press
    const handleKeyPress = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    // Handle takeover actions
    const handleTakeoverAction = (action) => {
        setTakeoverAction(action);
        setShowTakeoverModal(true);
        setTakeoverNote('');
    };

    const processTakeover = async () => {
        if (!selectedConversation || isProcessingTakeover) return;

        setIsProcessingTakeover(true);

        try {
            const endpoint = takeoverAction === 'takeover' ? 'takeover' : 'release';
            const response = await fetch(`/chatbots/${chatbot.id}/conversations/${selectedConversation.id}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                    [takeoverAction === 'takeover' ? 'takeover_note' : 'release_note']: takeoverNote,
                }),
            });

            if (response.ok) {
                const data = await response.json();

                // Update the selected conversation
                setSelectedConversation(data.conversation);

                // Update the conversation in the list
                setAllConversations(prev =>
                    prev.map(conv =>
                        conv.id === selectedConversation.id ? data.conversation : conv
                    )
                );

                toast.success(data.message);
                setShowTakeoverModal(false);
                setTakeoverNote('');
            } else {
                toast.error('Failed to process request. Please try again.');
            }
        } catch (error) {
            console.error('Error processing takeover:', error);
            toast.error('An error occurred. Please try again.');
        } finally {
            setIsProcessingTakeover(false);
        }
    };

    // Generate AI summary for the conversation and show in modal
    const generateSummary = async () => {
        if (!selectedConversation || isGeneratingSummary) return;

        setIsGeneratingSummary(true);

        try {
            const response = await fetch(`/chatbots/${chatbot.id}/conversations/${selectedConversation.id}/generate-summary`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });

            if (response.ok) {
                const data = await response.json();

                // Update the selected conversation
                setSelectedConversation(prev => ({
                    ...prev,
                    conversation_summary: data.summary
                }));

                // Update the conversation in the list
                setAllConversations(prev =>
                    prev.map(conv =>
                        conv.id === selectedConversation.id
                            ? { ...conv, conversation_summary: data.summary }
                            : conv
                    )
                );

                // Show the summary in modal
                setShowSummaryModal(true);
                toast.success('AI summary generated successfully!');
            } else {
                toast.error('Failed to generate summary. Please try again.');
            }
        } catch (error) {
            console.error('Error generating summary:', error);
            toast.error('An error occurred while generating summary.');
        } finally {
            setIsGeneratingSummary(false);
        }
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Conversations - ${chatbot.name}`}>
            <div className="h-screen flex bg-gray-50">
                {/* Left Sidebar - Conversation List */}
                <div className={`${sidebarCollapsed ? 'w-16' : 'w-1/3'} bg-white border-r border-gray-200 flex flex-col transition-all duration-300 overflow-hidden`}>
                    {/* Sidebar Header */}
                    <div className="p-4 border-b border-gray-200 flex items-center justify-between">
                        <button
                            onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                            title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        >
                            <Bars3Icon className="h-5 w-5 text-gray-600" />
                        </button>
                        {!sidebarCollapsed && (
                            <h3 className="font-medium text-gray-900">Conversations</h3>
                        )}
                    </div>

                    {/* Search Bar */}
                    {!sidebarCollapsed && (
                        <div className="p-4 border-b border-gray-200">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Search conversations"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-lg focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors text-sm"
                                />
                            </div>
                        </div>
                    )}

                    {/* Conversation List */}
                    <div
                        ref={scrollRef}
                        className="flex-1 overflow-y-auto"
                        onScroll={handleScroll}
                    >
                        {filteredConversations.map((conversation) => {
                            const lastMessage = conversation.messages?.[conversation.messages.length - 1];
                            const userMessage = conversation.messages?.find(msg => !msg.is_bot);
                            const userName = userMessage?.content?.split(' ')[0] || 'User';
                            const conversationType = getConversationType(conversation.session_id);

                            if (sidebarCollapsed) {
                                // Collapsed view - just show initials
                                return (
                                    <div
                                        key={conversation.id}
                                        onClick={() => {
                                            setSelectedConversation(conversation);
                                            // Auto-collapse sidebar when selecting a conversation
                                            setSidebarCollapsed(true);
                                        }}
                                        className={`p-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors flex justify-center ${
                                            selectedConversation?.id === conversation.id ? 'bg-blue-50 border-blue-200' : ''
                                        }`}
                                        title={`${userName} - ${conversationType}`}
                                    >
                                        <div className={`w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 ${
                                            conversationType === 'Test Chat'
                                                ? 'bg-gradient-to-br from-orange-500 to-red-600'
                                                : 'bg-gradient-to-br from-blue-500 to-purple-600'
                                        }`}>
                                            <span className="text-white font-semibold text-xs">
                                                {getInitials(userName)}
                                            </span>
                                        </div>
                                    </div>
                                );
                            }

                            // Expanded view
                            return (
                                <div
                                    key={conversation.id}
                                    onClick={() => {
                                        setSelectedConversation(conversation);
                                        // Auto-collapse sidebar when selecting a conversation
                                        setSidebarCollapsed(true);
                                    }}
                                    className={`p-4 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors ${
                                        selectedConversation?.id === conversation.id ? 'bg-blue-50 border-blue-200' : ''
                                    }`}
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className={`w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 ${
                                            conversationType === 'Test Chat'
                                                ? 'bg-gradient-to-br from-orange-500 to-red-600'
                                                : 'bg-gradient-to-br from-blue-500 to-purple-600'
                                        }`}>
                                            <span className="text-white font-semibold text-sm">
                                                {getInitials(userName)}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center space-x-2">
                                                    <h3 className="font-semibold text-gray-900 truncate">
                                                        {userName}
                                                    </h3>
                                                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                        conversationType === 'Test Chat'
                                                            ? 'bg-orange-100 text-orange-800'
                                                            : 'bg-green-100 text-green-800'
                                                    }`}>
                                                        {conversationType}
                                                    </span>
                                                </div>
                                                <span className="text-xs text-gray-500">
                                                    {formatTime(conversation.created_at)}
                                                </span>
                                            </div>
                                            <div className="flex items-center text-sm text-gray-500 mt-1">
                                                <PhoneIcon className="h-4 w-4 mr-1" />
                                                <span>{conversation.ip_address || 'Unknown IP'}</span>
                                            </div>
                                            {lastMessage && (
                                                <p className="text-sm text-gray-600 truncate mt-1">
                                                    {lastMessage.content}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}

                        {/* Loading indicator for scroll-based loading */}
                        {isLoadingMore && (
                            <div className="p-4 text-center">
                                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto"></div>
                            </div>
                        )}

                        {/* End of conversations indicator */}
                        {!hasMorePages && allConversations.length > 0 && !isLoadingMore && (
                            <div className="p-4 text-center text-gray-500 text-sm">
                                {sidebarCollapsed ? '•••' : 'All conversations loaded'}
                            </div>
                        )}

                        {filteredConversations.length === 0 && !isLoadingMore && (
                            <div className="p-8 text-center text-gray-500">
                                <UserIcon className="mx-auto h-12 w-12 text-gray-300 mb-4" />
                                <p>{sidebarCollapsed ? 'No chats' : 'No conversations found'}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="flex-1 flex flex-col">
                    {selectedConversation ? (
                        <>
                            {/* Chat Header */}
                            <div className="bg-white border-b border-gray-200 p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                            <span className="text-white font-semibold text-sm">
                                                {getInitials(selectedConversation.messages?.find(msg => !msg.is_bot)?.content?.split(' ')[0] || 'User')}
                                            </span>
                                        </div>
                                        <div>
                                            <h2 className="font-semibold text-gray-900">
                                                {selectedConversation.messages?.find(msg => !msg.is_bot)?.content?.split(' ')[0] || 'User'}
                                            </h2>
                                            <div className="flex items-center text-sm text-gray-500">
                                                <PhoneIcon className="h-4 w-4 mr-1" />
                                                <span>{selectedConversation.ip_address || 'Unknown IP'}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        {/* Takeover Status and Controls */}
                                        {selectedConversation && (
                                            <>
                                                <div className="flex items-center space-x-2">
                                                    {/* AI Summary Button - Always Available */}
                                                    <button
                                                        onClick={generateSummary}
                                                        disabled={isGeneratingSummary}
                                                        className="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50 transition-colors disabled:opacity-50"
                                                        title="Generate AI summary for this conversation"
                                                    >
                                                        {isGeneratingSummary ? (
                                                            <>
                                                                <div className="animate-spin rounded-full h-3 w-3 border-b-2 border-gray-600 mr-1"></div>
                                                                Generating...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <svg className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                </svg>
                                                            </>
                                                        )}
                                                    </button>

                                                    {/* Takeover Controls */}
                                                    {selectedConversation.is_human_takeover ? (
                                                        <div className="flex items-center space-x-2">
                                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                                <HandRaisedIcon className="h-3 w-3 mr-1" />
                                                                Human Control
                                                            </span>
                                                            <button
                                                                onClick={() => handleTakeoverAction('release')}
                                                                className="inline-flex items-center px-3 py-1.5 border border-orange-300 text-orange-700 text-xs font-medium rounded-md hover:bg-orange-50 transition-colors"
                                                                title="Release back to AI"
                                                            >
                                                                <XMarkIcon className="h-3 w-3 mr-1" />
                                                                Release
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <button
                                                            onClick={() => handleTakeoverAction('takeover')}
                                                            className="inline-flex items-center px-3 py-1.5 border border-blue-300 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-50 transition-colors"
                                                            title="Take over from AI"
                                                        >
                                                            <HandRaisedIcon className="h-3 w-3 mr-1" />
                                                            Take Over
                                                        </button>
                                                    )}
                                                </div>

                                                {/* Summary Available Indicator */}
                                                {selectedConversation.conversation_summary && (
                                                    <button
                                                        onClick={() => setShowSummaryModal(true)}
                                                        className="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors"
                                                        title="Click to view conversation summary"
                                                    >
                                                        <svg className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </button>
                                                )}
                                            </>
                                        )}

                                        <button
                                            onClick={() => setRightSidebarCollapsed(!rightSidebarCollapsed)}
                                            className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                            title={rightSidebarCollapsed ? 'Show contact info' : 'Hide contact info'}
                                        >
                                            <UserIcon className="h-5 w-5 text-gray-600" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Chat Messages */}
                            <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
                                {/* Human Takeover Notice */}
                                {selectedConversation.is_human_takeover && (
                                    <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                                        <div className="flex items-start">
                                            <div className="flex-shrink-0">
                                                <HandRaisedIcon className="h-5 w-5 text-orange-500 mt-0.5" />
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-sm font-medium text-orange-800">
                                                    Human Agent In Control
                                                </h3>
                                                <p className="mt-1 text-sm text-orange-700">
                                                    This conversation is now being handled by a human agent. All messages below show the conversation history before takeover.
                                                </p>
                                                {selectedConversation.taken_over_by && (
                                                    <p className="mt-1 text-xs text-orange-600">
                                                        Taken over by: {selectedConversation.taken_over_by.name} • {formatTime(selectedConversation.taken_over_at)}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {selectedConversation.messages?.map((message) => (
                                    <div key={message.id} className={`flex ${message.is_bot ? 'justify-start' : 'justify-end'}`}>
                                        <div className="flex items-start space-x-3 max-w-xs lg:max-w-md">
                                            {message.is_bot && (
                                                <div
                                                    className="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                                    style={{ backgroundColor: chatbot.appearance?.primary_color || '#4F46E5' }}
                                                >
                                                    {renderBotIcon('xs')}
                                                </div>
                                            )}

                                            <div className={`rounded-2xl px-4 py-3 ${
                                                message.is_bot
                                                    ? 'bg-white text-gray-800 shadow-sm'
                                                    : 'bg-blue-500 text-white'
                                            }`}>
                                                {message.is_bot ? (
                                                    <div className="text-sm leading-relaxed">
                                                        <ReactMarkdown
                                                            components={{
                                                                p: ({ children }) => <p className="mb-3 last:mb-0 leading-relaxed">{children}</p>,
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
                                                                code: ({ children, inline }) =>
                                                                    inline ? (
                                                                        <code className="bg-gray-100 px-1 py-0.5 rounded text-sm font-mono text-gray-800">
                                                                            {children}
                                                                        </code>
                                                                    ) : (
                                                                        <code className="block bg-gray-100 p-3 rounded-lg text-sm font-mono text-gray-800 overflow-x-auto">
                                                                            {children}
                                                                        </code>
                                                                    ),
                                                                pre: ({ children }) => <pre className="mb-3">{children}</pre>,
                                                                hr: () => <hr className="my-4 border-gray-300" />,
                                                                a: ({ children, href }) => (
                                                                    <a
                                                                        href={href}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-blue-600 hover:text-blue-800 underline font-medium"
                                                                    >
                                                                        {children}
                                                                    </a>
                                                                ),
                                                            }}
                                                        >
                                                            {message.content}
                                                        </ReactMarkdown>
                                                    </div>
                                                ) : (
                                                    <p className="text-sm leading-relaxed whitespace-pre-line">{message.content}</p>
                                                )}
                                                <div className="flex items-center justify-between mt-2">
                                                    <span className="text-xs opacity-70">
                                                        {formatTime(message.created_at)}
                                                    </span>
                                                    {!message.is_bot && (
                                                        <span className="text-xs opacity-70">via SMS</span>
                                                    )}
                                                </div>

                                                {message.sources && message.sources.length > 0 && (
                                                    <div className="mt-3 pt-3 border-t border-gray-200">
                                                        <p className="text-xs font-medium text-gray-500 mb-2">Sources:</p>
                                                        <div className="space-y-1">
                                                            {message.sources.map((source, index) => (
                                                                <div key={index} className="text-xs text-gray-600 bg-gray-100 rounded px-2 py-1">
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

                                            {!message.is_bot && (
                                                <div className="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <span className="text-white text-xs">
                                                        {getInitials(selectedConversation.messages?.find(msg => !msg.is_bot)?.content?.split(' ')[0] || 'User')}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {selectedConversation.messages?.length === 0 && (
                                    <div className="text-center text-gray-500 py-8">
                                        <p>No messages in this conversation</p>
                                    </div>
                                )}
                            </div>

                            {/* Chat Input */}
                            <div className="bg-white border-t border-gray-200 p-4">
                                <div className="flex items-end space-x-3">
                                    <div className="flex-1">
                                        <textarea
                                            value={newMessage}
                                            onChange={(e) => setNewMessage(e.target.value)}
                                            onKeyDown={handleKeyPress}
                                            placeholder="Type a message..."
                                            rows={1}
                                            className="w-full resize-none border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            style={{ minHeight: '40px', maxHeight: '120px' }}
                                            disabled={isSending}
                                        />
                                    </div>
                                    <button
                                        onClick={sendMessage}
                                        disabled={!newMessage.trim() || isSending}
                                        className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                                            newMessage.trim() && !isSending
                                                ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        }`}
                                    >
                                        {isSending ? 'Sending...' : 'Send'}
                                    </button>
                                </div>
                            </div>
                        </>
                    ) : (
                        /* No Conversation Selected */
                        <div className="flex-1 flex items-center justify-center bg-gray-50">
                            <div className="text-center text-gray-500">
                                <UserIcon className="mx-auto h-12 w-12 text-gray-300 mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-2">No conversation selected</h3>
                                <p>Select a conversation from the list to view the chat history</p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Right Sidebar - Contact Info */}
                {selectedConversation && !rightSidebarCollapsed && (
                    <div className="w-80 bg-white border-l border-gray-200 overflow-y-auto">
                        {/* General Info */}
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">General info</h3>
                                <button
                                    onClick={() => setRightSidebarCollapsed(true)}
                                    className="p-1 rounded hover:bg-gray-100"
                                >
                                    <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                                </button>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center space-x-3">
                                    <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                        <span className="text-white font-semibold">
                                            {getInitials(selectedConversation.messages?.find(msg => !msg.is_bot)?.content?.split(' ')[0] || 'User')}
                                        </span>
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900">
                                            {selectedConversation.messages?.find(msg => !msg.is_bot)?.content?.split(' ')[0] || 'User'}
                                        </h4>
                                        <p className="text-sm text-gray-500">
                                            {selectedConversation.ip_address || 'Unknown IP'}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <h5 className="text-sm font-medium text-gray-900 mb-1">Email</h5>
                                    <p className="text-sm text-gray-600">
                                        user@example.com
                                    </p>
                                </div>

                                <div>
                                    <h5 className="text-sm font-medium text-gray-900 mb-1">Date Created</h5>
                                    <p className="text-sm text-gray-600">
                                        {formatDate(selectedConversation.created_at)} • {formatTime(selectedConversation.created_at)}
                                    </p>
                                </div>

                                <div>
                                    <h5 className="text-sm font-medium text-gray-900 mb-1">Status</h5>
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active User
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Notes Section */}
                        <div className="border-t border-gray-200 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">Notes</h3>
                                <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                            </div>

                            <div className="space-y-3">
                                <div className="bg-gray-50 rounded-lg p-3">
                                    <p className="text-sm text-gray-700 mb-2">
                                        Customer interested in premium features. Provided demo access.
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {formatDate(selectedConversation.created_at)}
                                    </p>
                                </div>

                                <div className="bg-gray-50 rounded-lg p-3">
                                    <p className="text-sm text-gray-700 mb-2">
                                        Follow up scheduled for next week.
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {formatDate(selectedConversation.created_at)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Additional Info */}
                        <div className="border-t border-gray-200 p-6">
                            <div className="space-y-4">
                                <div>
                                    <button className="flex items-center justify-between w-full text-left">
                                        <span className="text-lg font-semibold text-gray-900">Additional Info</span>
                                        <ChevronUpIcon className="h-5 w-5 text-gray-400 transform rotate-180" />
                                    </button>
                                </div>

                                <div>
                                    <button className="flex items-center justify-between w-full text-left">
                                        <span className="text-lg font-semibold text-gray-900">Shared Files</span>
                                        <ChevronUpIcon className="h-5 w-5 text-gray-400 transform rotate-180" />
                                    </button>
                                </div>

                                <div>
                                    <button className="flex items-center justify-between w-full text-left">
                                        <span className="text-lg font-semibold text-gray-900">Shared Links</span>
                                        <ChevronUpIcon className="h-5 w-5 text-gray-400 transform rotate-180" />
                                    </button>
                                </div>

                                <div>
                                    <button className="flex items-center justify-between w-full text-left">
                                        <span className="text-lg font-semibold text-gray-900">Documentations</span>
                                        <ChevronUpIcon className="h-5 w-5 text-gray-400 transform rotate-180" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Takeover Modal */}
            {showTakeoverModal && (
                <div className="fixed inset-0 overflow-y-auto h-full w-full z-50" style={{backgroundColor: 'rgba(75, 85, 99, 0.8)'}}>
                    <div className="relative top-20 mx-auto p-5 w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900">
                                    {takeoverAction === 'takeover' ? 'Take Over Conversation' : 'Release Conversation'}
                                </h3>
                                <button
                                    onClick={() => setShowTakeoverModal(false)}
                                    className="text-gray-400 hover:text-gray-600 cursor-pointer"
                                >
                                    <XMarkIcon className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="mb-4">
                                <p className="text-sm text-gray-600 mb-3">
                                    {takeoverAction === 'takeover'
                                        ? 'You are about to take control of this conversation from the AI. The customer will now interact directly with you.'
                                        : 'You are about to release this conversation back to the AI. The AI will resume handling customer responses.'
                                    }
                                </p>

                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Add a note (optional):
                                </label>
                                <textarea
                                    value={takeoverNote}
                                    onChange={(e) => setTakeoverNote(e.target.value)}
                                    placeholder={takeoverAction === 'takeover'
                                        ? "e.g., Customer needs specialized support..."
                                        : "e.g., Issue resolved, returning to AI..."
                                    }
                                    rows={3}
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                            </div>

                            <div className="flex justify-end space-x-3">
                                <button
                                    onClick={() => setShowTakeoverModal(false)}
                                    disabled={isProcessingTakeover}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={processTakeover}
                                    disabled={isProcessingTakeover}
                                    className={`px-4 py-2 text-sm font-medium text-white rounded-md transition-colors disabled:opacity-50 ${
                                        takeoverAction === 'takeover'
                                            ? 'bg-blue-600 hover:bg-blue-700'
                                            : 'bg-orange-600 hover:bg-orange-700'
                                    }`}
                                >
                                    {isProcessingTakeover
                                        ? (takeoverAction === 'takeover' ? 'Taking Over...' : 'Releasing...')
                                        : (takeoverAction === 'takeover' ? 'Take Over' : 'Release')
                                    }
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* AI Summary Modal */}
            {showSummaryModal && selectedConversation?.conversation_summary && (
                <div className="fixed inset-0 overflow-y-auto h-full w-full z-50" style={{backgroundColor: 'rgba(75, 85, 99, 0.8)'}}>
                    <div className="relative top-20 mx-auto p-5 w-xl shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900 flex items-center">
                                    <svg className="h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Conversation Summary
                                </h3>
                                <button
                                    onClick={() => setShowSummaryModal(false)}
                                    className="text-gray-400 hover:text-gray-600 cursor-pointer"
                                >
                                    <XMarkIcon className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="mb-4">
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p className="text-sm text-blue-900 leading-relaxed">
                                        {selectedConversation.conversation_summary}
                                    </p>
                                </div>

                                <div className="mt-4 text-xs text-gray-500 text-center">
                                    <p>This summary was generated by AI to help understand the conversation context</p>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    onClick={() => setShowSummaryModal(false)}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </ChatbotLayout>
    );
}
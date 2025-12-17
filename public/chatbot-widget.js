(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChatbot);
    } else {
        initChatbot();
    }

    async function initChatbot() {
        if (!window.aiChatbot || !window.aiChatbot.chatbotId) {
            console.error('AI Chatbot: Configuration not found');
            return;
        }

        const config = window.aiChatbot.config || {};
        const chatbotId = window.aiChatbot.chatbotId;
        const tenantId = window.aiChatbot.tenantId || null;
        const baseUrl = window.aiChatbot.baseUrl;

        // Session management - 7 days persistence
        const SESSION_EXPIRY_DAYS = 7;
        const SESSION_KEY = 'ai_chatbot_widget_session_' + chatbotId + (tenantId ? '_' + tenantId : '');
        const CHAT_OPEN_STATE_KEY = 'ai_chatbot_is_open_' + chatbotId + (tenantId ? '_' + tenantId : '');
        
        let sessionId;
        
        // Check for existing session
        const storedSession = localStorage.getItem(SESSION_KEY);
        if (storedSession) {
            try {
                const sessionData = JSON.parse(storedSession);
                const expiryDate = new Date(sessionData.expiry);
                
                // Check if session is still valid
                if (expiryDate > new Date()) {
                    sessionId = sessionData.sessionId;
                    console.log('AI Chatbot: Existing widget session found:', sessionId);
                } else {
                    console.log('AI Chatbot: Widget session expired, creating new session');
                    sessionId = createNewWidgetSession();
                }
            } catch (error) {
                console.error('AI Chatbot: Failed to parse stored session, creating new session');
                sessionId = createNewWidgetSession();
            }
        } else {
            console.log('AI Chatbot: No widget session found, creating new session');
            sessionId = createNewWidgetSession();
        }
        
        function createNewWidgetSession() {
            const newSessionId = 'widget_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            
            // Calculate expiry date (7 days from now)
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + SESSION_EXPIRY_DAYS);
            
            // Store session in localStorage
            localStorage.setItem(SESSION_KEY, JSON.stringify({
                sessionId: newSessionId,
                expiry: expiryDate.toISOString()
            }));
            
            return newSessionId;
        }

        // Fetch chatbot appearance configuration
        let chatbotConfig = {};
        try {
            const response = await fetch(`${baseUrl}/api/widget-config/${chatbotId}`);
            if (response.ok) {
                chatbotConfig = await response.json();
            }
        } catch (error) {
            console.warn('Failed to load chatbot configuration:', error);
        }

        // Create widget styles
        const styles = `
            .ai-chatbot-widget {
                position: fixed;
                ${config.position === 'bottom-left' ? 'bottom: 20px; left: 20px;' : 'bottom: 20px; right: 20px;'}
                z-index: 10000;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            }

            .ai-chatbot-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: ${config.primaryColor || '#4F46E5'};
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease;
            }

            .ai-chatbot-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            }

            .ai-chatbot-iframe {
                width: 350px;
                height: 500px;
                border: none;
                border-radius: 12px;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
                position: absolute;
                bottom: 80px;
                ${config.position === 'bottom-left' ? 'left: 0;' : 'right: 0;'}
                display: none;
                background: white;
            }

            .ai-chatbot-iframe.show {
                display: block;
                animation: slideUp 0.3s ease-out;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @media (max-width: 768px) {
                .ai-chatbot-iframe {
                    width: calc(100vw - 40px);
                    height: calc(100vh - 100px);
                    ${config.position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;'}
                    bottom: 80px;
                }
            }
        `;

        // Inject styles
        const styleSheet = document.createElement('style');
        styleSheet.textContent = styles;
        document.head.appendChild(styleSheet);

        // Determine the icon to display
        let iconContent = '💬'; // Default
        if (chatbotConfig.appearance?.icon_type === 'emoji' && chatbotConfig.appearance?.selected_emoji) {
            iconContent = chatbotConfig.appearance.selected_emoji;
        } else if (chatbotConfig.appearance?.icon_type === 'svg' && chatbotConfig.appearance?.selected_svg) {
            const svgIcons = {
                chat: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" /></svg>',
                support: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C17.759 8.071 18 8.982 18 10z" clip-rule="evenodd" /></svg>',
                robot: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z" /></svg>',
                assistant: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                help: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>',
                message: '<svg style="width: 24px; height: 24px; color: white;" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" /><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" /></svg>'
            };
            iconContent = svgIcons[chatbotConfig.appearance.selected_svg] || svgIcons.chat;
        } else if (chatbotConfig.appearance?.icon_type === 'upload' && chatbotConfig.appearance?.custom_icon) {
            iconContent = `<img src="${chatbotConfig.appearance.custom_icon}" alt="Chat" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">`;
        }

        // Create widget HTML
        const widgetHTML = `
            <div class="ai-chatbot-widget">
                <button class="ai-chatbot-button" id="ai-chatbot-toggle">
                    ${iconContent}
                </button>
                <iframe
                    class="ai-chatbot-iframe"
                    id="ai-chatbot-iframe"
                    src="${baseUrl}/embed/${chatbotId}?session_id=${sessionId}${tenantId ? '&tenant_id=' + tenantId : ''}"
                    title="AI Chatbot">
                </iframe>
            </div>
        `;

        // Inject widget into page
        const widgetContainer = document.createElement('div');
        widgetContainer.innerHTML = widgetHTML;
        document.body.appendChild(widgetContainer);

        // Add event listeners
        const toggleButton = document.getElementById('ai-chatbot-toggle');
        const iframe = document.getElementById('ai-chatbot-iframe');
        
        if (!toggleButton || !iframe) {
            console.error('AI Chatbot: Could not find toggle button or iframe elements');
            return;
        }

        // Load chat open state from localStorage
        let isOpen = false;
        const storedOpenState = localStorage.getItem(CHAT_OPEN_STATE_KEY);
        console.log('AI Chatbot: Stored open state:', storedOpenState);
        
        if (storedOpenState === 'true') {
            isOpen = true;
            // Apply initial open state immediately
            iframe.classList.add('show');
            toggleButton.innerHTML = '✕';
            console.log('AI Chatbot: Restoring open state from localStorage - Chat will open automatically');
        } else {
            console.log('AI Chatbot: No saved open state or chat was closed - Starting in closed state');
        }

        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('Toggle button clicked, isOpen:', isOpen);

            if (isOpen) {
                iframe.classList.remove('show');
                toggleButton.innerHTML = iconContent;
                isOpen = false;
                localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
                console.log('Closing chat widget');
            } else {
                iframe.classList.add('show');
                toggleButton.innerHTML = '✕';
                isOpen = true;
                localStorage.setItem(CHAT_OPEN_STATE_KEY, 'true');
                console.log('Opening chat widget');
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(event) {
            // Only close if the widget is open AND the click is outside AND it's not a navigation click
            if (isOpen && !widgetContainer.contains(event.target)) {
                // Check if the clicked element is a link that will navigate away
                const clickedLink = event.target.closest('a');
                if (clickedLink && clickedLink.href && !clickedLink.target) {
                    // Don't close if clicking a link that will navigate - let the state persist
                    console.log('AI Chatbot: Link clicked, preserving open state for next page');
                    return;
                }
                
                iframe.classList.remove('show');
                toggleButton.innerHTML = iconContent;
                isOpen = false;
                localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
                console.log('AI Chatbot: Closing due to outside click');
            }
        });

        // Handle iframe messages (for advanced features)
        window.addEventListener('message', function(event) {
            if (event.origin !== baseUrl) return;

            if (event.data.type === 'ai-chatbot-close') {
                iframe.classList.remove('show');
                toggleButton.innerHTML = iconContent;
                isOpen = false;
                localStorage.setItem(CHAT_OPEN_STATE_KEY, 'false');
            } else if (event.data.type === 'ai-chatbot-resize') {
                // Handle dynamic resize if needed
                const { width, height } = event.data;
                if (width) iframe.style.width = width + 'px';
                if (height) iframe.style.height = height + 'px';
            }
        });

        console.log('AI Chatbot widget initialized successfully');
        console.log('Toggle button:', toggleButton);
        console.log('Iframe:', iframe);
        console.log('Widget container:', widgetContainer);
    }
})();
<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\Product;
use App\Models\Source;
use Illuminate\Support\Facades\Log;

class IntelligentConversationService
{
    protected OpenAIService $openAIService;
    protected ContextManagerService $contextManager;
    protected AIIntentAnalyzer $intentAnalyzer;

    public function __construct(OpenAIService $openAIService, ContextManagerService $contextManager, AIIntentAnalyzer $intentAnalyzer)
    {
        $this->openAIService = $openAIService;
        $this->contextManager = $contextManager;
        $this->intentAnalyzer = $intentAnalyzer;
    }

    /**
     * Generate intelligent response based on conversation context
     */
    public function generateResponse(
        string $userMessage,
        int $chatbotId,
        array $conversationHistory = [],
        array $sources = [],
        array $products = []
    ): string {
        $responseData = $this->generateResponseWithUsage($userMessage, $chatbotId, $conversationHistory, $sources, $products);
        return $responseData['content'];
    }

    /**
     * Generate intelligent response with token usage tracking
     */
    public function generateResponseWithUsage(
        string $userMessage,
        int $chatbotId,
        array $conversationHistory = [],
        array $sources = [],
        array $products = []
    ): array {
        // Analyze user intent
        $intent = $this->analyzeUserIntent($userMessage, $conversationHistory);

        // Get conversation state
        $conversationState = $this->getConversationState($conversationHistory);

        // Build system prompt based on intent and context
        $systemPrompt = $this->buildSystemPrompt($intent, $conversationState, $chatbotId);

        // For pure greetings, don't include knowledge base context to avoid confusion
        $context = [];
        if ($intent['primary_intent'] !== 'greeting') {
            // Assemble relevant context only for non-greeting queries
            $context = $this->contextManager->assembleContext($sources, $products, $this->formatConversationHistory($conversationHistory));
        }

        // Generate response using OpenAI with usage tracking
        $response = $this->openAIService->generateChatResponseWithUsage($systemPrompt, $userMessage, $context);

        // Add intent information to the response
        $response['intent'] = $intent;
        $response['show_contact_info'] = $intent['is_contact_request'] || $intent['primary_intent'] === 'technical_issue';

        return $response;
    }

    /**
     * Analyze user intent using AI-powered analysis with fallback
     */
    private function analyzeUserIntent(string $userMessage, array $conversationHistory = []): array
    {
        // Use AI Intent Analyzer for accurate classification
        $aiIntent = $this->intentAnalyzer->analyzeIntent($userMessage, $conversationHistory);

        // Enhanced intent analysis with additional reference detection
        $hasReferences = $this->containsReferences($userMessage) || $aiIntent['has_references'];

        return [
            'primary_intent' => $aiIntent['primary_intent'],
            'all_intents' => $aiIntent['all_intents'],
            'confidence' => $aiIntent['confidence'],
            'is_follow_up' => $aiIntent['is_follow_up'],
            'has_references' => $hasReferences,
            'is_contact_request' => $aiIntent['is_contact_request']
        ];
    }

    /**
     * Get conversation state
     */
    private function getConversationState(array $conversationHistory): array
    {
        $messageCount = count($conversationHistory);
        $hasBeenGreeted = false;
        $lastBotMessage = null;
        $topics = [];

        foreach ($conversationHistory as $message) {
            if (str_starts_with($message, 'Assistant:')) {
                $lastBotMessage = $message;
                if ($this->containsGreeting($message)) {
                    $hasBeenGreeted = true;
                }
            }

            // Extract topics discussed
            $topics = array_merge($topics, $this->extractTopics($message));
        }

        return [
            'message_count' => $messageCount,
            'has_been_greeted' => $hasBeenGreeted,
            'is_new_conversation' => $messageCount === 0,
            'last_bot_message' => $lastBotMessage,
            'discussed_topics' => array_unique($topics)
        ];
    }

    /**
     * Build system prompt based on intent and context
     */
    private function buildSystemPrompt(array $intent, array $conversationState, int $chatbotId): string
    {
        $chatbot = Chatbot::find($chatbotId);
        $botName = $chatbot->name ?? 'AI Assistant';
        $customMessage = $chatbot->initial_message ?? '';

        $basePrompt = "You are {$botName}, a knowledgeable and helpful AI assistant.";

        if ($customMessage) {
            $basePrompt .= " " . $customMessage;
        }

        // Add conversation guidelines
        $guidelines = "\n\nCONVERSATION GUIDELINES:\n";

        if ($conversationState['has_been_greeted']) {
            $guidelines .= "- STRICTLY FORBIDDEN: Do not use ANY greeting words including 'Hello', 'Hi', 'Hey', 'Welcome', 'Great to connect', 'Nice to meet'\n";
            $guidelines .= "- STRICTLY FORBIDDEN: Do not start with 'How can I assist' or 'How can I help' - user already greeted\n";
            $guidelines .= "- Start responses directly with the answer or helpful information\n";
            $guidelines .= "- Focus only on addressing their specific question or request\n";
            $guidelines .= "- Be helpful but skip all pleasantries since greeting already occurred\n";
        } else {
            $guidelines .= "- Provide a warm, professional greeting\n";
            $guidelines .= "- Set a helpful tone for the conversation\n";
        }

        // Intent-specific guidelines
        switch ($intent['primary_intent']) {
            case 'greeting':
                $guidelines .= "- This is a GREETING from the user\n";
                $guidelines .= "- Respond with a warm, friendly greeting\n";
                $guidelines .= "- Keep it simple and welcoming - just say hello and offer help\n";
                $guidelines .= "- Example: 'Hello! How can I help you today?'\n";
                $guidelines .= "- Do NOT provide any product information, features, or knowledge base content\n";
                $guidelines .= "- Do NOT start explaining anything - wait for the user's actual question\n";
                break;

            case 'contact_request':
                $guidelines .= "- This is a CONTACT REQUEST - the user wants to reach a person or office\n";
                $guidelines .= "- Identify WHICH office fits their need (admission, registrar, accounts, a department)\n";
                $guidelines .= "- Do NOT answer with programme, fee or academic details instead\n";
                $guidelines .= "- Keep it short - the contact details themselves are shown separately below your reply\n";
                break;

            case 'follow_up':
                $guidelines .= "- This is a FOLLOW-UP question referring to previous conversation context\n";
                $guidelines .= "- The user is asking for more details about something already discussed\n";
                $guidelines .= "- Maintain strict topic continuity - stay on the same programme/topic\n";
                $guidelines .= "- Do NOT introduce a new programme or department - elaborate on the existing one\n";
                $guidelines .= "- Reference what was previously mentioned to maintain context\n";
                break;

            case 'help_request':
                $guidelines .= "- The user is asking for help - respond with 'How can I help you?' or 'What do you need assistance with?'\n";
                $guidelines .= "- Mention the areas you can cover: admission, programmes, fees, routines and results, campus facilities\n";
                $guidelines .= "- Ask clarifying questions to understand their specific needs\n";
                break;

            case 'admission':
                $guidelines .= "- This is an ADMISSION question from a prospective student\n";
                $guidelines .= "- Lead with the concrete answer: eligibility, required documents, or the deadline\n";
                $guidelines .= "- State dates and deadlines EXACTLY as they appear in the knowledge base - never estimate one\n";
                $guidelines .= "- If the admission cycle or deadline is not in the knowledge base, say so and point them to the admission office\n";
                $guidelines .= "- Close with the next practical step the student should take\n";
                break;

            case 'fees_scholarship':
                $guidelines .= "- This is a question about FEES, PAYMENT or SCHOLARSHIP\n";
                $guidelines .= "- CRITICAL: quote every amount, percentage and waiver condition EXACTLY as written in the knowledge base\n";
                $guidelines .= "- NEVER estimate, calculate, convert or round a figure that is not written there\n";
                $guidelines .= "- If the exact figure is missing, say you cannot confirm it and refer them to the accounts or admission office\n";
                $guidelines .= "- State what a quoted figure covers (per credit, per semester, one-time) so it cannot be misread\n";
                $guidelines .= "- Mention waiver or scholarship eligibility conditions whenever you quote a discounted figure\n";
                break;

            case 'program_inquiry':
                $guidelines .= "- This is a question about PROGRAMMES, DEPARTMENTS or COURSES\n";
                $guidelines .= "- Use the knowledge base for programme names, durations, credit counts and department details\n";
                $guidelines .= "- Group related programmes by school or faculty so the list is easy to scan\n";
                $guidelines .= "- Do NOT invent a programme, specialisation or course code that is not in the knowledge base\n";
                $guidelines .= "- Offer to go deeper on any single programme the student names\n";
                break;

            case 'academic_info':
                $guidelines .= "- This is a question about ACADEMIC RECORDS or SCHEDULES (routine, exam, result, transcript, registration)\n";
                $guidelines .= "- Reproduce routine and schedule rows exactly, keeping course, time, room and teacher together\n";
                $guidelines .= "- You cannot look up an individual student's personal record - be clear about that and direct them to the correct office\n";
                $guidelines .= "- Treat a complaint (result not published, registration blocked) as an academic matter, NOT a website fault\n";
                $guidelines .= "- Do NOT suggest browser troubleshooting for these questions\n";
                break;

            case 'campus_facility':
                $guidelines .= "- This is a question about CAMPUS FACILITIES (hostel, transport, library, labs, cafeteria, sports, medical)\n";
                $guidelines .= "- Answer from the knowledge base, including location, timing and eligibility where available\n";
                $guidelines .= "- If a facility differs by campus or by gender, state which one your answer applies to\n";
                $guidelines .= "- Do NOT assume a facility exists just because it is common at other universities\n";
                break;

            case 'technical_issue':
                $guidelines .= "- The user reports a fault in the ONLINE PORTAL OR WEBSITE itself\n";
                $guidelines .= "- Use troubleshooting steps from the knowledge base when they exist\n";
                $guidelines .= "- Only suggest generic steps (reload the page, try another browser) when nothing specific is available\n";
                $guidelines .= "- Do NOT ask for a password, one-time code or any other credential\n";
                $guidelines .= "- IMPORTANT: Always offer the IT/support desk if the issue persists\n";
                $guidelines .= "- Be empathetic about the user's frustration\n";
                break;

            case 'general':
            default:
                $guidelines .= "- Answer from the knowledge base and stay within what the university has actually published\n";
                $guidelines .= "- If the knowledge base does not cover it, say so plainly instead of guessing\n";
                $guidelines .= "- Offer the nearest thing you can help with, or point them to the right office\n";
                break;
        }

        // Add special handling for contact requests
        if ($intent['is_contact_request']) {
            $guidelines .= "\n!!! CRITICAL: CONTACT REQUEST !!!\n";
            $guidelines .= "User wants to CONTACT the business, NOT learn about product features.\n";
            $guidelines .= "Do NOT explain product features or technical details.\n";
            $guidelines .= "Focus ONLY on helping them connect with the business.\n";

            $guidelines .= "End with: 'Please use the contact information provided below to get in touch with our team.'\n";
            $guidelines .= "DO NOT include specific contact details in your response - they will be shown separately.\n";
        }

        // Add special handling for technical issues
        if ($intent['primary_intent'] === 'technical_issue') {
            $guidelines .= "\n!!! TECHNICAL ISSUE HANDLING !!!\n";
            $guidelines .= "User is experiencing a technical problem. Be empathetic and helpful.\n";
            $guidelines .= "Provide troubleshooting steps and solutions from the knowledge base.\n";
            $guidelines .= "End with: 'If the issue persists, please contact our support team using the contact information provided below.'\n";
            $guidelines .= "DO NOT include specific contact details in your response - they will be shown separately.\n";
        }

        // Add special handling for follow-up and reference queries
        if ($intent['is_follow_up'] || $intent['has_references']) {
            $guidelines .= "\n!!! CRITICAL: CONVERSATION CONTINUITY !!!\n";
            $guidelines .= "This is a follow-up or reference-based question.\n";
            $guidelines .= "You MUST maintain the same topic/product from the previous discussion.\n";
            $guidelines .= "Do NOT introduce new products or change the subject.\n";
            $guidelines .= "Build upon what was already mentioned in the conversation.\n";
        }

        $guidelines .= "\n- Keep responses concise and natural\n";
        $guidelines .= "- Avoid repeating information already covered in the conversation\n";
        $guidelines .= "- Ask engaging follow-up questions when appropriate\n";
        $guidelines .= "- Be conversational but professional\n";

        // Final reinforcement for non-greeting responses
        if ($conversationState['has_been_greeted']) {
            $guidelines .= "\n!!! CRITICAL REMINDER !!!\n";
            $guidelines .= "The user has ALREADY been greeted in this conversation.\n";
            $guidelines .= "You must NOT use Hello, Hi, Hey, Welcome, or any greeting phrases.\n";
            $guidelines .= "Start your response with the actual helpful content only.\n";
        }

        return $basePrompt . $guidelines;
    }

    /**
     * Check if message is a greeting
     */
    private function isGreeting(string $message): bool
    {
        $greetingPatterns = [
            'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
            'greetings', 'yo', 'hiya', 'howdy', 'sup', 'what\'s up', 'how are you'
        ];

        foreach ($greetingPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is a help request
     */
    private function isHelpRequest(string $message): bool
    {
        $helpPatterns = [
            'help me', 'can you help', 'i need help', 'assist me', 'support',
            'how can you help', 'what can you do', 'help'
        ];

        foreach ($helpPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is ecommerce-related
     */
    private function isEcommerceQuery(string $message): bool
    {
        $ecommercePatterns = [
            'ecommerce', 'e-commerce', 'online store', 'shop', 'shopping',
            'buy', 'purchase', 'order', 'payment', 'checkout', 'cart',
            'product', 'inventory', 'shipping', 'delivery', 'return'
        ];

        foreach ($ecommercePatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is product inquiry
     */
    private function isProductInquiry(string $message): bool
    {
        $productPatterns = [
            'product', 'item', 'service', 'price', 'cost', 'feature',
            'specification', 'detail', 'available', 'stock'
        ];

        foreach ($productPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is information seeking
     */
    private function isInformationSeeking(string $message): bool
    {
        $questionPatterns = [
            'what', 'how', 'when', 'where', 'why', 'which', 'who',
            'tell me', 'explain', 'describe', 'show me'
        ];

        foreach ($questionPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate intent confidence
     */
    private function calculateIntentConfidence(array $intents, string $message): float
    {
        if (empty($intents)) {
            return 0.5; // Neutral confidence for general queries
        }

        // Simple confidence based on number of matching patterns
        return min(1.0, count($intents) * 0.3 + 0.4);
    }

    /**
     * Check if message contains greeting
     */
    private function containsGreeting(string $message): bool
    {
        $greetingWords = [
            'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
            'welcome', 'greetings', 'hiya', 'howdy', 'great to connect', 'nice to meet',
            'how can i assist', 'how can i help', 'what can i help'
        ];
        $lowerMessage = strtolower($message);

        foreach ($greetingWords as $word) {
            if (str_contains($lowerMessage, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract topics from message
     */
    private function extractTopics(string $message): array
    {
        $topics = [];
        $lowerMessage = strtolower($message);

        // Define topic keywords
        $topicKeywords = [
            'ecommerce' => ['ecommerce', 'e-commerce', 'online store', 'shop'],
            'products' => ['product', 'item', 'service'],
            'orders' => ['order', 'purchase', 'buy'],
            'support' => ['help', 'support', 'assistance'],
        ];

        foreach ($topicKeywords as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowerMessage, $keyword)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        return $topics;
    }

    /**
     * Format conversation history for context
     */
    private function formatConversationHistory(array $conversationHistory): string
    {
        if (empty($conversationHistory)) {
            return '';
        }

        // Increase to 8 messages for better context continuity
        $recentHistory = array_slice($conversationHistory, -8);

        return "RECENT CONVERSATION:\n" . implode("\n", $recentHistory) . "\n\n";
    }

    /**
     * Detect if message is a follow-up question
     */
    private function isFollowUpQuestion(string $message, array $conversationHistory): bool
    {
        $followUpPatterns = [
            '/^(tell me|explain|what about|how about|more about|details about|can you tell|can you explain)/i',
            '/^(yes,?\s*)?(sure,?\s*)?(explain|tell|show|describe)/i',
            '/\b(it|this|that|them|they)\b.*\b(work|works|feature|features|test|testing|use|using|price|cost|benefit)/i',
            '/^(how (can|do|does)|what (is|are|does)|where (can|do))/i',
            '/\b(more info|more details|additional info|further info)/i'
        ];

        foreach ($followUpPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        // Check if conversation history suggests this is a follow-up
        if (!empty($conversationHistory)) {
            $lastBotMessage = '';
            for ($i = count($conversationHistory) - 1; $i >= 0; $i--) {
                if (str_starts_with($conversationHistory[$i], 'Assistant:')) {
                    $lastBotMessage = $conversationHistory[$i];
                    break;
                }
            }

            // If bot recently mentioned a product/service and user asks follow-up
            if (!empty($lastBotMessage) && strlen($message) < 200) {
                $hasProductMention = preg_match('/\b[A-Z][a-z]+(?:[A-Z][a-z]+)*\b/', $lastBotMessage);
                $isShortQuery = str_word_count($message) <= 10;
                if ($hasProductMention && $isShortQuery) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if message contains references (pronouns, demonstratives)
     */
    private function containsReferences(string $message): bool
    {
        $referencePatterns = [
            '/\b(it|this|that|these|those|them|they)\b/i',
            '/\b(the (one|product|service|script|solution|platform))\b/i',
            '/\b(above|mentioned|previous|earlier)\b/i'
        ];

        foreach ($referencePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build contact information string from chatbot settings
     */
    private function buildContactInformation(Chatbot $chatbot): ?string
    {
        $contactSettings = $chatbot->contact_settings;

        // Check if contact settings are enabled and configured
        if (!$contactSettings || !($contactSettings['enabled'] ?? false)) {
            return null;
        }

        $contactInfo = '';

        // Add custom support message if provided
        if (!empty($contactSettings['support_message'])) {
            $contactInfo .= $contactSettings['support_message'] . "\n\n";
        } else {
            $contactInfo .= "Here are the ways you can contact our support team:\n\n";
        }

        $hasContactMethods = false;

        // Add WhatsApp contact
        if (!empty($contactSettings['whatsapp_number'])) {
            $whatsappNumber = $contactSettings['whatsapp_number'];
            $contactInfo .= "• **WhatsApp**: {$whatsappNumber}\n";
            $hasContactMethods = true;
        }

        // Add Phone contact
        if (!empty($contactSettings['phone_number'])) {
            $phoneNumber = $contactSettings['phone_number'];
            $contactInfo .= "• **Phone**: {$phoneNumber}\n";
            $hasContactMethods = true;
        }

        // Add Email contact
        if (!empty($contactSettings['email_address'])) {
            $emailAddress = $contactSettings['email_address'];
            $contactInfo .= "• **Email**: {$emailAddress}\n";
            $hasContactMethods = true;
        }

        // Add Support Email
        if (!empty($contactSettings['support_email'])) {
            $supportEmail = $contactSettings['support_email'];
            $contactInfo .= "• **Support Email**: {$supportEmail}\n";
            $hasContactMethods = true;
        }

        // Add Support URL
        if (!empty($contactSettings['support_url'])) {
            $supportUrl = $contactSettings['support_url'];
            $contactInfo .= "• **Support Portal**: {$supportUrl}\n";
            $hasContactMethods = true;
        }

        // Add business hours if provided
        if (!empty($contactSettings['business_hours'])) {
            $contactInfo .= "\n**Business Hours**: {$contactSettings['business_hours']}\n";
        }

        // Add closing message
        if ($hasContactMethods) {
            $contactInfo .= "\nFeel free to reach out through any of these channels for immediate assistance!";
            return $contactInfo;
        }

        return null;
    }
}
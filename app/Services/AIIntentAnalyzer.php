<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIIntentAnalyzer
{
    protected OpenAIService $openAIService;
    protected array $intentCache = [];

    // Cache duration for common intent patterns (5 minutes)
    protected int $cacheMinutes = 5;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Analyze user intent using AI with caching for efficiency
     */
    public function analyzeIntent(string $userMessage, array $conversationHistory = []): array
    {
        $cacheKey = $this->getCacheKey($userMessage);

        // Check cache first for efficiency
        if ($cached = Cache::get($cacheKey)) {
            Log::info('Intent cache hit', ['message' => substr($userMessage, 0, 50)]);
            return $cached;
        }

        try {
            $intent = $this->performAIAnalysis($userMessage, $conversationHistory);

            // Cache the result for efficiency
            Cache::put($cacheKey, $intent, now()->addMinutes($this->cacheMinutes));

            Log::info('AI Intent analyzed', [
                'message' => substr($userMessage, 0, 50),
                'intent' => $intent['primary_intent'],
                'confidence' => $intent['confidence']
            ]);

            return $intent;

        } catch (\Exception $e) {
            Log::error('AI Intent Analysis failed, using fallback', [
                'error' => $e->getMessage(),
                'message' => substr($userMessage, 0, 50)
            ]);

            // Fallback to basic pattern matching
            return $this->fallbackAnalysis($userMessage);
        }
    }

    /**
     * Perform AI-powered intent analysis using optimized prompt
     */
    private function performAIAnalysis(string $userMessage, array $conversationHistory = []): array
    {
        $systemPrompt = $this->buildOptimizedPrompt();

        // Include recent conversation context for better accuracy
        $context = '';
        if (!empty($conversationHistory)) {
            $recentMessages = array_slice($conversationHistory, -3); // Last 3 messages for context
            $context = "\n\nRecent conversation:\n" . implode("\n", $recentMessages);
        }

        $fullPrompt = $userMessage . $context;

        $response = $this->openAIService->generateChatResponse($systemPrompt, $fullPrompt);

        return $this->parseAIResponse($response);
    }

    /**
     * Build optimized system prompt for fast intent classification
     */
    private function buildOptimizedPrompt(): string
    {
        return "Classify user intent. Return ONLY: intent|confidence

Intents:
- contact_request: user wants to communicate/contact business (whatsapp, phone, email)
- product_inquiry: asking about features, demos, technical details
- technical_issue: reporting problems, bugs, errors, or seeking troubleshooting help
- follow_up: continuing previous discussion, asking for more details
- help_request: general assistance, how can you help
- greeting: hello, hi, good morning
- general: other queries

Examples:
- \"communicate through whatsapp\" → contact_request|0.95
- \"what features does it have\" → product_inquiry|0.90
- \"button not working\" → technical_issue|0.90
- \"update freezes\" → technical_issue|0.85
- \"tell me more about that\" → follow_up|0.85

Respond format: intent|confidence";
    }

    /**
     * Parse AI response into structured intent data
     */
    private function parseAIResponse(string $response): array
    {
        $parts = explode('|', trim($response));

        $intent = $parts[0] ?? 'general';
        $confidence = isset($parts[1]) ? (float) $parts[1] : 0.5;

        // Validate intent
        $validIntents = ['contact_request', 'product_inquiry', 'technical_issue', 'follow_up', 'help_request', 'greeting', 'general'];
        if (!in_array($intent, $validIntents)) {
            $intent = 'general';
            $confidence = 0.3;
        }

        return [
            'primary_intent' => $intent,
            'confidence' => $confidence,
            'is_contact_request' => $intent === 'contact_request',
            'is_follow_up' => $intent === 'follow_up',
            'has_references' => $intent === 'follow_up', // Follow-ups often have references
            'all_intents' => [$intent]
        ];
    }

    /**
     * Fallback analysis using simple pattern matching
     */
    private function fallbackAnalysis(string $userMessage): array
    {
        $lowerMessage = strtolower(trim($userMessage));

        // Contact request patterns
        if ($this->matchesContactRequest($lowerMessage)) {
            return [
                'primary_intent' => 'contact_request',
                'confidence' => 0.8,
                'is_contact_request' => true,
                'is_follow_up' => false,
                'has_references' => false,
                'all_intents' => ['contact_request']
            ];
        }

        // Technical issue patterns
        if ($this->matchesTechnicalIssue($lowerMessage)) {
            return [
                'primary_intent' => 'technical_issue',
                'confidence' => 0.8,
                'is_contact_request' => false,
                'is_follow_up' => false,
                'has_references' => false,
                'all_intents' => ['technical_issue']
            ];
        }

        // Follow-up patterns
        if ($this->matchesFollowUp($lowerMessage)) {
            return [
                'primary_intent' => 'follow_up',
                'confidence' => 0.7,
                'is_contact_request' => false,
                'is_follow_up' => true,
                'has_references' => true,
                'all_intents' => ['follow_up']
            ];
        }

        // Greeting patterns
        if ($this->matchesGreeting($lowerMessage)) {
            return [
                'primary_intent' => 'greeting',
                'confidence' => 0.9,
                'is_contact_request' => false,
                'is_follow_up' => false,
                'has_references' => false,
                'all_intents' => ['greeting']
            ];
        }

        // Default to general
        return [
            'primary_intent' => 'general',
            'confidence' => 0.5,
            'is_contact_request' => false,
            'is_follow_up' => false,
            'has_references' => false,
            'all_intents' => ['general']
        ];
    }

    /**
     * Check if message matches contact request patterns
     */
    private function matchesContactRequest(string $message): bool
    {
        $patterns = [
            'communicate through whatsapp',
            'contact via whatsapp',
            'whatsapp number',
            'reach you on whatsapp',
            'talk on whatsapp',
            'contact information',
            'get in touch',
            'how to contact',
            'speak with someone',
            'call you',
            'email address'
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message matches follow-up patterns
     */
    private function matchesFollowUp(string $message): bool
    {
        $patterns = [
            'tell me more',
            'explain more',
            'more details',
            'more about',
            'how about',
            'what about',
            'can you tell',
            'yes, sure',
            'it work',
            'this work',
            'that work'
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message matches technical issue patterns
     */
    private function matchesTechnicalIssue(string $message): bool
    {
        $patterns = [
            'not working',
            'doesn\'t work',
            'not functioning',
            'freezes',
            'frozen',
            'freeze',
            'crashed',
            'error',
            'bug',
            'problem',
            'issue',
            'broken',
            'failed',
            'won\'t update',
            'can\'t update',
            'update button',
            'button freezes',
            'nothing happens',
            'nothing happened',
            'not responding',
            'php version',
            'version compatibility',
            'after upgrade',
            'after update',
            'stopped working',
            'clear cache',
            'troubleshoot'
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message matches greeting patterns
     */
    private function matchesGreeting(string $message): bool
    {
        // Only match if greeting is the entire message or starts with greeting
        $patterns = [
            '/^(hello|hi|hey|good morning|good afternoon|good evening|greetings|yo|hiya|howdy)[!.?\s]*$/i',
            '/^(hello|hi|hey)\s+(there|everyone|guys)[!.?\s]*$/i',
            '/^(hi|hello|hey)$/i' // Exact match for simple greetings
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate cache key for intent analysis
     */
    private function getCacheKey(string $message): string
    {
        // Use first 100 chars and hash for consistent caching
        $key = substr(strtolower(trim($message)), 0, 100);
        return 'intent_' . md5($key);
    }
}
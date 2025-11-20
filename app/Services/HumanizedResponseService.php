<?php

namespace App\Services;

use App\Services\OpenAIService;
use App\Models\ConversationPattern;
use Illuminate\Support\Facades\Log;

class HumanizedResponseService
{
    protected OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }
    /**
     * Generate a humanized greeting response
     */
    public function generateGreetingResponse(string $question, string $customInitialMessage = null, array $conversationHistory = []): string
    {
        $lowerQuestion = strtolower(trim($question));

        // Check if user has already been greeted in this conversation
        $hasBeenGreeted = $this->hasBeenGreeted($conversationHistory);

        // Detect the type of greeting
        $greetingType = $this->detectGreetingType($lowerQuestion);

        // Generate contextual response based on greeting type and conversation state
        $response = $this->generateContextualGreeting($greetingType, $question, $customInitialMessage, $hasBeenGreeted);

        // Clean up multiple greetings to fix the "Hi there! Hello!" issue
        return $this->cleanupMultipleGreetings($response);
    }

    /**
     * Detect the type of greeting
     */
    private function detectGreetingType(string $lowerQuestion): string
    {
        $greetingPatterns = [
            'hello' => ['hello', 'hi there', 'hey there', 'good day', 'greetings'],
            'hi' => ['hi', 'hey', 'hello there', 'hiya'],
            'good_morning' => ['good morning', 'morning'],
            'good_afternoon' => ['good afternoon', 'afternoon'],
            'good_evening' => ['good evening', 'evening'],
            'how_are_you' => ['how are you', 'how are you doing', 'how do you do', 'how\'s it going', 'whats up', 'what\'s up'],
            'help' => ['help', 'can you help', 'i need help', 'assist me', 'support'],
            'what_can_you_do' => ['what can you do', 'what do you do', 'how can you help', 'what are you capable of'],
            'who_are_you' => ['who are you', 'what are you', 'introduce yourself', 'tell me about yourself'],
        ];

        foreach ($greetingPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lowerQuestion, $pattern)) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    /**
     * Generate contextual greeting based on type
     */
    private function generateContextualGreeting(string $greetingType, string $originalQuestion, string $customInitialMessage = null, bool $hasBeenGreeted = false): string
    {
        // Common conversation starters and their natural responses
        $commonStarters = [
            // Basic greetings
            'hello', 'hi', 'hey', 'hiya', 'hai',

            // Time-based greetings
            'good morning', 'morning', 'good afternoon', 'afternoon',
            'good evening', 'evening', 'good night',

            // Casual check-ins
            'how are you', 'how are you doing', 'how do you do',
            'how\'s it going', 'whats up', 'what\'s up', 'sup',
            'how you doing', 'how\'s everything', 'how are things',

            // Help requests
            'help', 'can you help', 'i need help', 'assist me',
            'support', 'help me', 'can you assist',

            // Capability questions
            'what can you do', 'what do you do', 'how can you help',
            'what are you capable of', 'what can you help with',

            // Introduction requests
            'who are you', 'what are you', 'introduce yourself',
            'tell me about yourself', 'what is this',

            // Conversation starters
            'whats this about', 'what\'s this about', 'explain this',
            'im here', 'i\'m here', 'im new here', 'i\'m new here'
        ];

        // Get single natural response based on greeting type and conversation state
        switch ($greetingType) {
            case 'hello':
            case 'hi':
                if ($hasBeenGreeted) {
                    $responses = [
                        ($customInitialMessage ?: "How can I help you?"),
                        ($customInitialMessage ?: "What can I assist you with?"),
                        ($customInitialMessage ?: "What would you like to know?")
                    ];
                } else {
                    $responses = [
                        "Hello! " . ($customInitialMessage ?: "I'm here to help you with any questions. What's on your mind?"),
                        "Hi there! " . ($customInitialMessage ?: "Great to meet you! How can I assist you today?"),
                        "Hey! " . ($customInitialMessage ?: "Welcome! What can I help you with?")
                    ];
                }
                break;

            case 'good_morning':
                $responses = [
                    "Good morning! " . ($customInitialMessage ?: "Hope you're having a great start to your day. How can I help?"),
                    "Morning! " . ($customInitialMessage ?: "What can I assist you with today?")
                ];
                break;

            case 'good_afternoon':
                $responses = [
                    "Good afternoon! " . ($customInitialMessage ?: "Hope your day is going well. How can I help?"),
                    "Afternoon! " . ($customInitialMessage ?: "What can I assist you with?")
                ];
                break;

            case 'good_evening':
                $responses = [
                    "Good evening! " . ($customInitialMessage ?: "How can I help you tonight?"),
                    "Evening! " . ($customInitialMessage ?: "What can I help you with?")
                ];
                break;

            case 'how_are_you':
                $responses = [
                    "I'm doing great, thanks for asking! " . ($customInitialMessage ?: "How can I help you today?"),
                    "I'm fantastic! " . ($customInitialMessage ?: "What brings you here?"),
                    "I'm wonderful, thank you! " . ($customInitialMessage ?: "How are you doing?")
                ];
                break;

            case 'help':
                $responses = [
                    "Absolutely! " . ($customInitialMessage ?: "I'm here to help. What do you need assistance with?"),
                    "Of course! " . ($customInitialMessage ?: "What can I help you with?")
                ];
                break;

            case 'what_can_you_do':
                $responses = [
                    "I can help with quite a lot! " . ($customInitialMessage ?: "What specific area are you interested in?"),
                    "Great question! " . ($customInitialMessage ?: "What kind of help are you looking for?")
                ];
                break;

            case 'who_are_you':
                $responses = [
                    ($customInitialMessage ?: "I'm your AI assistant, here to help with your questions.") . " Nice to meet you!",
                    ($customInitialMessage ?: "I'm an AI assistant designed to help you.") . " What would you like to know?"
                ];
                break;

            default:
                $responses = [
                    ($customInitialMessage ?: "Hello! I'm here to help.") . " What can I do for you?",
                    ($customInitialMessage ?: "Hi! Great to connect with you.") . " How can I assist?",
                    ($customInitialMessage ?: "Welcome!") . " What questions do you have?"
                ];
        }

        // Return single response without multiple greetings
        return $responses[array_rand($responses)];
    }

    /**
     * Get current time of day
     */
    private function getTimeOfDay(): string
    {
        $hour = (int) date('H');

        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } elseif ($hour >= 17 && $hour < 22) {
            return 'evening';
        } else {
            return 'night';
        }
    }

    /**
     * Get time-based greeting
     */
    private function getTimeBasedGreeting(string $timeOfDay): string
    {
        $greetings = [
            'morning' => ['Good morning!', 'Morning!', 'Hope you\'re having a great morning!'],
            'afternoon' => ['Good afternoon!', 'Afternoon!', 'Hope your day is going well!'],
            'evening' => ['Good evening!', 'Evening!', 'Hope you\'ve had a good day!'],
            'night' => ['Good evening!', 'Evening!', 'Working late tonight?']
        ];

        $timeGreetings = $greetings[$timeOfDay] ?? ['Hello!'];
        return $timeGreetings[array_rand($timeGreetings)];
    }

    /**
     * Check if user has already been greeted in conversation history
     */
    private function hasBeenGreeted(array $conversationHistory): bool
    {
        foreach ($conversationHistory as $message) {
            if (str_starts_with($message, 'Assistant:')) {
                if ($this->containsGreeting($message)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if message contains greeting
     */
    private function containsGreeting(string $message): bool
    {
        $greetingWords = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'welcome', 'greetings'];
        $lowerMessage = strtolower($message);

        foreach ($greetingWords as $word) {
            if (str_contains($lowerMessage, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message contains a greeting
     */
    public function isGreeting(string $message): bool
    {
        $lowerMessage = strtolower(trim($message));

        // Comprehensive list of 100+ conversation starters plus additional variations
        $greetingPatterns = [
            // Your 100+ list
            'hello', 'hi', 'hey', 'howdy', 'greetings', 'yo', 'hiya', 'sup', 'what\'s up', 'whats up',
            'how\'s it going', 'hows it going', 'good morning', 'good afternoon', 'good evening',
            'hey there', 'what\'s good', 'whats good', 'how are you', 'hola', 'aloha', 'salut', 'ciao',
            'yo yo', 'what\'s happening', 'whats happening', 'how\'s it hangin\'', 'hows it hangin',
            'good to see you', 'what\'s new', 'whats new', 'hey buddy', 'hi there', 'how\'s everything',
            'hows everything', 'what\'s cooking', 'whats cooking', 'yo dude', 'g\'day', 'gday',
            'how\'s life', 'hows life', 'what\'s the vibe', 'whats the vibe', 'hey friend', 'hi ya',
            'what\'s poppin\'', 'whats poppin', 'how you doing', 'good day', 'hey mate',
            'what\'s the word', 'whats the word', 'hola amigo', 'yo man', 'how\'s things', 'hows things',
            'what\'s up doc', 'whats up doc', 'hey pal', 'hi folks', 'what\'s the deal', 'whats the deal',
            'how\'s it rollin\'', 'hows it rollin', 'yo bro', 'hey y\'all', 'hey yall', 'what\'s cracking',
            'whats cracking', 'how\'s your day', 'hows your day', 'good to catch up', 'what\'s the scoop',
            'whats the scoop', 'hey there stranger', 'salaam', 'namaste', 'bonjour', 'hej', 'hallo', 'oi',
            'shalom', 'konnichiwa', 'sawasdee', 'ni hao', 'how\'s it been', 'hows it been',
            'what\'s going on', 'whats going on', 'yo homie', 'hey there buddy', 'how you holdin\' up',
            'how you holdin up', 'what\'s the buzz', 'whats the buzz', 'hi friend', 'good to meet you',
            'what\'s the story', 'whats the story', 'how\'s your vibe', 'hows your vibe', 'yo what\'s good',
            'yo whats good', 'hey fam', 'what\'s up fam', 'whats up fam', 'how\'s it flowin\'',
            'hows it flowin', 'good seeing ya', 'what\'s the plan', 'whats the plan', 'hey you',
            'hi all', 'what\'s shakin\'', 'whats shakin', 'how\'s the scene', 'hows the scene',
            'yo peeps', 'hey folks', 'what\'s the latest', 'whats the latest', 'how\'s it chillin\'',
            'hows it chillin', 'good vibes', 'what\'s the 411', 'whats the 411', 'hey there pal',
            'how\'s your world', 'hows your world', 'what\'s up mate', 'whats up mate', 'yo fam',
            'hi everyone', 'what\'s the haps', 'whats the haps', 'how you faring', 'hey crew',
            'what\'s the good word', 'whats the good word',

            // Additional variations and similar phrases
            'wassup', 'wazzup', 'wsup', 'whaddup', 'what up', 'heya', 'heyya', 'hai', 'helo',
            'morning', 'afternoon', 'evening', 'mornin', 'evenin', 'top o\' the morning',
            'how do you do', 'how ya doin', 'how ya been', 'howdy partner', 'howdy there',
            'pleased to meet you', 'nice to meet you', 'good to see ya', 'long time no see',
            'how have you been', 'whassup', 'what it do', 'what it is', 'how goes it',
            'how\'s tricks', 'hows tricks', 'what\'s the word on the street', 'any news',
            'how\'s your morning', 'how\'s your afternoon', 'how\'s your evening',
            'lovely morning', 'beautiful day', 'fine morning', 'great day',
            'top of the morning', 'rise and shine', 'wakey wakey',

            // International greetings and variations
            'buenos dias', 'buenas tardes', 'buenas noches', 'como estas', 'que tal',
            'guten tag', 'guten morgen', 'wie geht\'s', 'bon matin', 'bonsoir',
            'ca va', 'comment allez-vous', 'ohayo', 'konbanwa', 'annyeonghaseyo',
            'as-salamu alaykum', 'shalom aleichem', 'sawubona', 'jambo', 'sannu',

            // Casual internet/text speak
            'sup dude', 'sup bro', 'sup man', 'sup girl', 'hey girl', 'hey boi', 'hey gurl',
            'hiiii', 'heyyyy', 'hellooo', 'yooo', 'heyyy', 'what\'s poppin\'',

            // Help and support requests
            'help', 'can you help', 'i need help', 'assist me', 'support', 'help me', 'can you assist',
            'i need assistance', 'could you help', 'help please', 'assistance needed',
            'can you support', 'looking for help', 'need some help',

            // Capability questions
            'what can you do', 'what do you do', 'how can you help', 'what are you capable of',
            'what can you help with', 'what are your capabilities', 'what services do you provide',
            'what can you assist with', 'how do you work', 'what\'s your purpose',

            // Introduction requests
            'who are you', 'what are you', 'introduce yourself', 'tell me about yourself', 'what is this',
            'who am i talking to', 'what kind of bot are you', 'are you human', 'are you ai',
            'what\'s your name', 'whats your name',

            // Conversation starters
            'whats this about', 'what\'s this about', 'explain this', 'im here', 'i\'m here',
            'im new here', 'i\'m new here', 'first time here', 'new user', 'just joined',
            'getting started', 'where do i start', 'how does this work'
        ];

        foreach ($greetingPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean up multiple greetings in response
     */
    private function cleanupMultipleGreetings(string $response): string
    {
        // Common greeting words that might appear multiple times
        $greetingWords = [
            'hello', 'hi', 'hey', 'hiya', 'howdy', 'greetings',
            'good morning', 'good afternoon', 'good evening',
            'morning', 'afternoon', 'evening'
        ];

        // Pattern to match multiple greetings (case insensitive)
        $patterns = [];
        foreach ($greetingWords as $greeting) {
            // Match greeting word followed by punctuation, then another greeting
            $patterns[] = '/\b' . preg_quote($greeting, '/') . '[!\s]*\s+(?:there!\s+)?(?:' . implode('|', array_map(function($g) { return preg_quote($g, '/'); }, $greetingWords)) . ')\b/i';
        }

        foreach ($patterns as $pattern) {
            $response = preg_replace($pattern, '', $response);
        }

        // Clean up extra spaces and punctuation
        $response = preg_replace('/\s+/', ' ', $response);
        $response = preg_replace('/^\s*[!\s]*/', '', $response);
        $response = trim($response);

        // Ensure response starts with a capital letter
        if (!empty($response)) {
            $response = ucfirst($response);
        }

        return $response;
    }

    /**
     * Generate follow-up suggestions
     */
    public function generateFollowUpSuggestions(): array
    {
        return [
            "What topics can you help me with?",
            "Tell me about your knowledge base",
            "How can you assist me?",
            "What kind of questions can I ask?"
        ];
    }
}
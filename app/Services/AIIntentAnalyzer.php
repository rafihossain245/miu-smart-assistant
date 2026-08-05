<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIIntentAnalyzer
{
    /**
     * Every intent the classifier may return. Anything else the model invents
     * is coerced to 'general'. Keep this in sync with buildOptimizedPrompt()
     * and the guideline switch in IntelligentConversationService.
     */
    public const VALID_INTENTS = [
        'contact_request',
        'admission',
        'fees_scholarship',
        'program_inquiry',
        'academic_info',
        'campus_facility',
        'technical_issue',
        'follow_up',
        'help_request',
        'greeting',
        'general',
    ];

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
        return "Classify the intent of a question asked to a university assistant. Return ONLY: intent|confidence

Intents:
- contact_request: wants to reach a person or office (phone, whatsapp, email, office hours, address)
- admission: applying, eligibility, requirements, deadlines, admission test, transfer
- fees_scholarship: tuition, fees, payment, instalments, waiver, scholarship, financial aid
- program_inquiry: programs, departments, courses, syllabus, credits, duration, faculty
- academic_info: class routine, exam schedule, results, transcript, semester, registration, calendar
- campus_facility: hostel, transport, library, labs, cafeteria, sports, medical, prayer room
- technical_issue: the online portal or website itself is failing (login, password, page errors)
- follow_up: continuing the previous topic, asking for more detail
- help_request: general assistance, what can you do
- greeting: hello, hi, assalamu alaikum
- general: anything else

Examples:
- \"how can I contact the admission office\" → contact_request|0.95
- \"what is the last date to apply\" → admission|0.95
- \"how much is the BBA tuition fee\" → fees_scholarship|0.95
- \"what programs does the university offer\" → program_inquiry|0.90
- \"when will the result be published\" → academic_info|0.90
- \"is there a hostel for female students\" → campus_facility|0.90
- \"I cannot log in to the student portal\" → technical_issue|0.90
- \"tell me more about that\" → follow_up|0.85

A complaint about an academic outcome (result not published, application rejected) is
academic_info or admission, NOT technical_issue. Reserve technical_issue for the portal itself.

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
        if (!in_array($intent, self::VALID_INTENTS)) {
            $intent = 'general';
            $confidence = 0.3;
        }

        return $this->intentPayload($intent, $confidence);
    }

    /**
     * Build the structured intent array every caller expects
     */
    private function intentPayload(string $intent, float $confidence): array
    {
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

        // Ordered most-specific first - the first matcher to hit wins. Topic
        // matchers sit above program_inquiry because "BBA tuition fee" should
        // answer as a fee question, not a generic programme question.
        $matchers = [
            ['greeting', 0.9, fn ($m) => $this->matchesGreeting($m)],
            ['contact_request', 0.8, fn ($m) => $this->matchesContactRequest($m)],
            ['follow_up', 0.7, fn ($m) => $this->matchesFollowUp($m)],
            ['fees_scholarship', 0.75, fn ($m) => $this->matchesFeesScholarship($m)],
            ['admission', 0.75, fn ($m) => $this->matchesAdmission($m)],
            ['academic_info', 0.75, fn ($m) => $this->matchesAcademicInfo($m)],
            ['campus_facility', 0.75, fn ($m) => $this->matchesCampusFacility($m)],
            ['technical_issue', 0.8, fn ($m) => $this->matchesTechnicalIssue($m)],
            ['program_inquiry', 0.7, fn ($m) => $this->matchesProgramInquiry($m)],
        ];

        foreach ($matchers as [$intent, $confidence, $matches]) {
            if ($matches($lowerMessage)) {
                return $this->intentPayload($intent, $confidence);
            }
        }

        // Default to general
        return $this->intentPayload('general', 0.5);
    }

    /**
     * Whole-word keyword match. The word boundary matters for short academic
     * tokens - a plain str_contains would fire "fee" inside "feel".
     */
    private function matchesAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message matches contact request patterns
     */
    private function matchesContactRequest(string $message): bool
    {
        return $this->matchesAny($message, [
            'communicate through whatsapp',
            'contact via whatsapp',
            'whatsapp number',
            'reach you on whatsapp',
            'talk on whatsapp',
            'contact information',
            'contact number',
            'contact details',
            'phone number',
            'mobile number',
            'hotline',
            'helpline',
            'get in touch',
            'how to contact',
            'how can i contact',
            'speak with someone',
            'talk to someone',
            'call you',
            'email address',
            'office hours',
            'admission office',
            'registrar office',
            'visit the campus',
            'campus address',
        ]);
    }

    /**
     * Check if message is about applying to the university
     */
    private function matchesAdmission(string $message): bool
    {
        return $this->matchesAny($message, [
            'admission',
            'admitted',
            'apply',
            'application',
            'how to apply',
            'enroll',
            'enrol',
            'enrollment',
            'enrolment',
            'eligibility',
            'eligible',
            'requirement',
            'requirements',
            'deadline',
            'last date',
            'admission test',
            'entrance exam',
            'viva',
            'gpa requirement',
            'ssc',
            'hsc',
            'a level',
            'o level',
            'credit transfer',
        ]);
    }

    /**
     * Check if message is about money - fees, payment or financial support
     */
    private function matchesFeesScholarship(string $message): bool
    {
        return $this->matchesAny($message, [
            'fee',
            'fees',
            'tuition',
            'how much',
            'cost',
            'charge',
            'payment',
            'pay',
            'instalment',
            'installment',
            'scholarship',
            'waiver',
            'discount',
            'financial aid',
            'stipend',
            'funding',
            'refund',
        ]);
    }

    /**
     * Check if message is about programmes, departments or courses
     */
    private function matchesProgramInquiry(string $message): bool
    {
        return $this->matchesAny($message, [
            'program',
            'programme',
            'programs',
            'programmes',
            'course',
            'courses',
            'subject',
            'department',
            'faculty',
            'major',
            'syllabus',
            'curriculum',
            'credit',
            'credits',
            'duration',
            'bachelor',
            'bachelors',
            'masters',
            'degree',
            'honours',
            'diploma',
            'bba',
            'mba',
            'cse',
            'eee',
            'llb',
            'pharmacy',
            'architecture',
        ]);
    }

    /**
     * Check if message is about ongoing academic life - routines, exams, results
     */
    private function matchesAcademicInfo(string $message): bool
    {
        return $this->matchesAny($message, [
            'routine',
            'class routine',
            'class schedule',
            'timetable',
            'exam',
            'exams',
            'exam date',
            'midterm',
            'final exam',
            'result',
            'results',
            'grade',
            'grades',
            'gpa',
            'cgpa',
            'transcript',
            'certificate',
            'semester',
            'attendance',
            'registration',
            'academic calendar',
            'holiday',
            'convocation',
        ]);
    }

    /**
     * Check if message is about campus facilities and services
     */
    private function matchesCampusFacility(string $message): bool
    {
        return $this->matchesAny($message, [
            'hostel',
            'accommodation',
            'dormitory',
            'transport',
            'bus',
            'shuttle',
            'library',
            'lab',
            'laboratory',
            'cafeteria',
            'canteen',
            'campus',
            'gym',
            'sports',
            'club',
            'medical',
            'prayer room',
            'mosque',
            'wifi',
            'parking',
        ]);
    }

    /**
     * Check if message matches follow-up patterns
     */
    private function matchesFollowUp(string $message): bool
    {
        return $this->matchesAny($message, [
            'tell me more',
            'explain more',
            'more details',
            'more info',
            'more about',
            'how about',
            'what about',
            'can you tell',
            'anything else',
            'yes, sure',
        ]);
    }

    /**
     * Check if message reports a fault in the online portal or website.
     *
     * Deliberately narrow. Bare words like "problem", "issue" or "failed" are
     * not enough: "problem with my admission" and "I failed the exam" are
     * academic questions, and answering them with troubleshooting steps is
     * worse than useless. A fault word only counts when it is paired with a
     * system the university actually runs.
     */
    private function matchesTechnicalIssue(string $message): bool
    {
        $unambiguous = [
            'forgot password',
            'reset password',
            'change password',
            'cannot login',
            'can\'t login',
            'cannot log in',
            'can\'t log in',
            'unable to login',
            'unable to log in',
            'login problem',
            'login issue',
            'account locked',
            'otp not',
            'verification code not',
        ];

        if ($this->matchesAny($message, $unambiguous)) {
            return true;
        }

        $systems = [
            'portal',
            'website',
            'site',
            'web site',
            'online form',
            'online application',
            'student account',
            'login page',
            'dashboard',
            'link',
        ];

        $faults = [
            'not working',
            'doesn\'t work',
            'does not work',
            'not loading',
            'not opening',
            'not responding',
            'error',
            'down',
            'broken',
            'stuck',
            'crashed',
            'blank',
            'cannot access',
            'can\'t access',
        ];

        return $this->matchesAny($message, $systems) && $this->matchesAny($message, $faults);
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
            '/^(assalamu\s*alaikum|assalamualaikum|salam|slm)[!.?\s]*$/i',
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
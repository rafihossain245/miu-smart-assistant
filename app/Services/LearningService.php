<?php

namespace App\Services;

use App\Models\ChatbotLearningData;
use App\Models\Source;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class LearningService
{
    protected OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Record interaction for learning purposes
     */
    public function recordInteraction(
        int $chatbotId,
        int $conversationId,
        string $userQuery,
        string $botResponse,
        array $contextUsed = [],
        float $similarityScore = null,
        int $responseTimeMs = null
    ): ChatbotLearningData {
        // Extract query patterns
        $queryPatterns = $this->extractQueryPatterns($userQuery);

        return ChatbotLearningData::create([
            'chatbot_id' => $chatbotId,
            'conversation_id' => $conversationId,
            'user_query' => $userQuery,
            'bot_response' => $botResponse,
            'context_used' => $contextUsed,
            'similarity_score' => $similarityScore,
            'response_time_ms' => $responseTimeMs,
            'query_patterns' => $queryPatterns,
            'interaction_type' => 'question',
        ]);
    }

    /**
     * Record user feedback on a response
     */
    public function recordFeedback(
        int $learningDataId,
        bool $wasHelpful,
        array $additionalFeedback = []
    ): void {
        ChatbotLearningData::where('id', $learningDataId)->update([
            'was_helpful' => $wasHelpful,
            'user_feedback' => $additionalFeedback,
        ]);
    }

    /**
     * Record user correction/better answer
     */
    public function recordCorrection(
        int $learningDataId,
        string $userCorrection
    ): void {
        $learningData = ChatbotLearningData::find($learningDataId);
        if ($learningData) {
            $learningData->update([
                'user_correction' => $userCorrection,
                'interaction_type' => 'correction',
            ]);

            // Auto-create a new source from user correction if it's substantial
            $this->createSourceFromCorrection($learningData, $userCorrection);
        }
    }

    /**
     * Extract patterns from user queries for learning
     */
    protected function extractQueryPatterns(string $query): array
    {
        $patterns = [];

        // Extract question types
        $questionWords = ['what', 'how', 'why', 'when', 'where', 'who', 'which'];
        foreach ($questionWords as $word) {
            if (stripos($query, $word) !== false) {
                $patterns['question_types'][] = $word;
            }
        }

        // Extract intent
        if (preg_match('/\b(show|list|find|get|tell|explain|describe)\b/i', $query, $matches)) {
            $patterns['intent'] = strtolower($matches[1]);
        }

        // Extract entities/topics
        $topics = [];
        $commonTopics = [
            'ecommerce', 'script', 'php', 'laravel', 'crm', 'alternative', 'tool',
            'software', 'platform', 'service', 'api', 'database', 'framework'
        ];

        foreach ($commonTopics as $topic) {
            if (stripos($query, $topic) !== false) {
                $topics[] = $topic;
            }
        }
        $patterns['topics'] = $topics;

        // Extract length and complexity
        $patterns['word_count'] = str_word_count($query);
        $patterns['character_count'] = strlen($query);
        $patterns['has_numbers'] = preg_match('/\d/', $query) ? true : false;

        return $patterns;
    }

    /**
     * Create a new source from user correction if substantial enough
     */
    protected function createSourceFromCorrection(ChatbotLearningData $learningData, string $correction): void
    {
        // Only create source if correction is substantial (>100 characters)
        if (strlen($correction) > 100) {
            try {
                // Generate embedding for the correction
                $embedding = $this->openAIService->generateEmbedding($correction);

                Source::create([
                    'chatbot_id' => $learningData->chatbot_id,
                    'type' => 'text',
                    'title' => 'User Correction: ' . substr($learningData->user_query, 0, 50) . '...',
                    'content' => $correction,
                    'embedding' => $embedding,
                    'status' => 'completed',
                ]);

                Log::info("Created new source from user correction for chatbot {$learningData->chatbot_id}");
            } catch (\Exception $e) {
                Log::error("Failed to create source from user correction: " . $e->getMessage());
            }
        }
    }

    /**
     * Analyze learning data to improve responses
     */
    public function analyzeLearningData(int $chatbotId): array
    {
        $learningData = ChatbotLearningData::forChatbot($chatbotId)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $analysis = [
            'total_interactions' => $learningData->count(),
            'helpful_responses' => $learningData->where('was_helpful', true)->count(),
            'unhelpful_responses' => $learningData->where('was_helpful', false)->count(),
            'corrections_received' => $learningData->whereNotNull('user_correction')->count(),
            'common_query_patterns' => $this->analyzeQueryPatterns($learningData),
            'low_similarity_queries' => $learningData->where('similarity_score', '<', 0.3)->count(),
            'average_response_time' => $learningData->avg('response_time_ms'),
        ];

        return $analysis;
    }

    /**
     * Analyze common query patterns
     */
    protected function analyzeQueryPatterns($learningData): array
    {
        $allPatterns = $learningData->pluck('query_patterns')->filter();
        $patterns = [
            'common_question_types' => [],
            'common_intents' => [],
            'common_topics' => [],
        ];

        foreach ($allPatterns as $pattern) {
            if (isset($pattern['question_types'])) {
                foreach ($pattern['question_types'] as $type) {
                    $patterns['common_question_types'][$type] =
                        ($patterns['common_question_types'][$type] ?? 0) + 1;
                }
            }

            if (isset($pattern['intent'])) {
                $patterns['common_intents'][$pattern['intent']] =
                    ($patterns['common_intents'][$pattern['intent']] ?? 0) + 1;
            }

            if (isset($pattern['topics'])) {
                foreach ($pattern['topics'] as $topic) {
                    $patterns['common_topics'][$topic] =
                        ($patterns['common_topics'][$topic] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency
        arsort($patterns['common_question_types']);
        arsort($patterns['common_intents']);
        arsort($patterns['common_topics']);

        return $patterns;
    }

    /**
     * Get similar past interactions to improve current response
     */
    public function getSimilarInteractions(int $chatbotId, string $query, int $limit = 3): array
    {
        try {
            // Generate embedding for current query
            $queryEmbedding = $this->openAIService->generateEmbedding($query);

            // Get recent successful interactions
            $interactions = ChatbotLearningData::forChatbot($chatbotId)
                ->where('was_helpful', true)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            $similarities = [];

            foreach ($interactions as $interaction) {
                // Calculate similarity with stored interactions
                $interactionEmbedding = $this->openAIService->generateEmbedding($interaction->user_query);
                $similarity = $this->calculateCosineSimilarity($queryEmbedding, $interactionEmbedding);

                if ($similarity > 0.7) { // High similarity threshold
                    $similarities[] = [
                        'interaction' => $interaction,
                        'similarity' => $similarity,
                    ];
                }
            }

            // Sort by similarity and return top results
            usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            return array_slice($similarities, 0, $limit);

        } catch (\Exception $e) {
            Log::error("Error getting similar interactions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    protected function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += pow($vectorA[$i], 2);
            $magnitudeB += pow($vectorB[$i], 2);
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Generate adaptive response based on learning data
     */
    public function generateAdaptiveResponse(
        int $chatbotId,
        string $query,
        array $baseContext,
        float $baseSimilarityScore
    ): array {
        // Get similar past interactions
        $similarInteractions = $this->getSimilarInteractions($chatbotId, $query);

        $adaptiveContext = $baseContext;
        $confidenceBoost = 0;

        if (!empty($similarInteractions)) {
            // Add context from successful past interactions
            foreach ($similarInteractions as $similar) {
                $interaction = $similar['interaction'];
                $adaptiveContext[] = "Past successful response to similar query: " . $interaction->bot_response;
                $confidenceBoost += $similar['similarity'] * 0.1;
            }
        }

        // Check for user corrections that might apply
        $corrections = ChatbotLearningData::forChatbot($chatbotId)
            ->whereNotNull('user_correction')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        foreach ($corrections as $correction) {
            $correctionEmbedding = $this->openAIService->generateEmbedding($correction->user_query);
            $queryEmbedding = $this->openAIService->generateEmbedding($query);
            $similarity = $this->calculateCosineSimilarity($queryEmbedding, $correctionEmbedding);

            if ($similarity > 0.6) {
                $adaptiveContext[] = "User provided correction for similar query: " . $correction->user_correction;
                $confidenceBoost += 0.2;
            }
        }

        return [
            'adaptive_context' => $adaptiveContext,
            'confidence_score' => min(1.0, $baseSimilarityScore + $confidenceBoost),
            'learning_applied' => !empty($similarInteractions) || $confidenceBoost > 0,
        ];
    }
}
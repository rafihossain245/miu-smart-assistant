<?php

namespace App\Services;

use App\Models\Source;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ChatbotLearningData;
use App\Models\Chatbot;
use App\Models\Product;
use App\Models\TokenUsage;
use App\Services\LearningService;
use App\Services\HumanizedResponseService;
use App\Services\EmbeddingCacheService;
use App\Services\IntelligentConversationService;
use Illuminate\Support\Facades\Log;

class RAGService
{
    protected OpenAIService $openAIService;
    protected LearningService $learningService;
    protected HumanizedResponseService $humanizedResponseService;
    protected EmbeddingCacheService $embeddingCache;
    protected IntelligentConversationService $intelligentConversation;

    public function __construct(
        OpenAIService $openAIService,
        LearningService $learningService,
        HumanizedResponseService $humanizedResponseService,
        EmbeddingCacheService $embeddingCache,
        IntelligentConversationService $intelligentConversation
    ) {
        $this->openAIService = $openAIService;
        $this->learningService = $learningService;
        $this->humanizedResponseService = $humanizedResponseService;
        $this->embeddingCache = $embeddingCache;
        $this->intelligentConversation = $intelligentConversation;
    }

    public function generateResponse(string $question, int $chatbotId, string $sessionId = null, string $ipAddress = null): array
    {
        $startTime = microtime(true);

        try {
            // Get chatbot instance to access custom initial message
            $chatbot = Chatbot::findOrFail($chatbotId);

            // Get or create conversation to access message history
            $conversation = Conversation::firstOrCreate([
                'chatbot_id' => $chatbotId,
                'session_id' => $sessionId,
            ], [
                'ip_address' => $ipAddress,
            ]);

            // Get recent conversation history for context (increased to 15 messages for better continuity)
            $recentMessages = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get()
                ->reverse() // Oldest first for proper context
                ->map(function ($message) {
                    return ($message->is_bot ? 'Assistant: ' : 'User: ') . $message->content;
                })->toArray();

            // Check if this is a simple greeting or help request that doesn't need RAG
            if ($this->isSimpleInteraction($question)) {
                // Use intelligent conversation service for greetings and simple requests
                $responseData = $this->intelligentConversation->generateResponseWithUsage(
                    $question,
                    $chatbotId,
                    $recentMessages,
                    [], // No sources needed for simple interactions
                    []  // No products needed for simple interactions
                );

                $response = $responseData['content'];

                $sources = [];

                $conversation->messages()->create([
                    'content' => $question,
                    'is_bot' => false,
                    'sources' => [],
                ]);

                $conversation->messages()->create([
                    'content' => $response,
                    'is_bot' => true,
                    'sources' => $sources,
                ]);

                // Log token usage
                if (isset($responseData['usage'])) {
                    $this->logTokenUsage($chatbotId, $conversation->id, $responseData);
                }

                return [
                    'reply' => $response,
                    'sources' => $sources,
                    'show_contact_info' => $responseData['show_contact_info'] ?? false,
                ];
            }

            $conversationContext = !empty($recentMessages)
                ? "Recent conversation history:\n" . implode("\n", $recentMessages) . "\n\n"
                : '';

            // Enhanced conversation analysis for better context awareness
            $isFollowUpQuestion = $this->detectFollowUpQuestion($question, $recentMessages);
            $hasReferences = $this->detectReferences($question);

            // Skip hardcoded company search - rely on knowledge base prioritization instead

            // Check if we have any completed sources first
            $completedSourcesCount = Source::where('chatbot_id', $chatbotId)
                ->where('status', 'completed')
                ->whereNotNull('embedding')
                ->count();

            if ($completedSourcesCount === 0) {
                Log::info("No completed sources found for chatbot {$chatbotId}");
                $response = "I'm still learning from your knowledge base. Please sync your sources first or wait for processing to complete.";
                $sources = [];
            } else {
                // Generate embedding for the user question (using cache)
                // For follow-up questions, enhance query with conversation context
                $enhancedQuery = $question;
                if ($isFollowUpQuestion || $hasReferences) {
                    $enhancedQuery = $this->enhanceQueryWithContext($question, $recentMessages);
                }

                $queryEmbedding = $this->embeddingCache->getOrGenerateEmbedding($enhancedQuery);

                // Check if this is a technical issue query
                $isTechnicalIssue = $this->isTechnicalIssueQuery($question);

                // Find similar sources from the chatbot's knowledge base
                if ($isTechnicalIssue) {
                    Log::info("Technical issue detected, prioritizing technical solutions");
                    $similarSources = Source::findSimilarForTechnicalIssues($queryEmbedding, $chatbotId, 5);
                } else {
                    $similarSources = Source::findSimilar($queryEmbedding, $chatbotId, 5);
                }

                Log::info("Found {$similarSources->count()} similar sources for chatbot {$chatbotId}", [
                    'question' => $question,
                    'distances' => $similarSources->pluck('distance')->toArray(),
                    'first_distance' => $similarSources->first()?->distance
                ]);

                // Check if we have relevant sources - increased threshold to 0.8 for better matching
                if ($similarSources->isEmpty() || $similarSources->first()->distance > 0.8) {
                    Log::info("No relevant sources found", [
                        'empty' => $similarSources->isEmpty(),
                        'first_distance' => $similarSources->first()?->distance,
                        'threshold' => 0.8
                    ]);

                    // Generate intelligent response even without specific context
                    $systemPrompt = $this->buildSystemPrompt($chatbotId);

                    // Get some info about what the knowledge base contains
                    $knowledgeBaseTopics = Source::where('chatbot_id', $chatbotId)
                        ->where('status', 'completed')
                        ->limit(5)
                        ->pluck('title')
                        ->toArray();

                    $topicsInfo = !empty($knowledgeBaseTopics)
                        ? "My knowledge base covers topics like: " . implode(", ", $knowledgeBaseTopics)
                        : "My knowledge base contains various resources";

                    // Check if we have relevant products even when no knowledge base match
                    $relevantProducts = $this->findRelevantProducts($question, $chatbotId);

                    if (!empty($relevantProducts)) {
                        // Generate AI response with product recommendations
                        $systemPrompt = $this->buildSystemPrompt($chatbotId);
                        $productsContext = $this->formatProductsContext($relevantProducts);
                        $contextArray = [
                            $conversationContext,
                            "While I don't have specific information in my knowledge base about this topic, here are some relevant products/services that might help:",
                            $productsContext
                        ];

                        $response = $this->openAIService->generateChatResponse($systemPrompt, $question, array_filter($contextArray));

                        Log::info("Generated product-based response without knowledge base match", [
                            'product_count' => count($relevantProducts),
                            'product_names' => array_column($relevantProducts, 'name')
                        ]);
                    } else {
                        // Use a more polite fallback response
                        $politeResponses = [
                            "I'm really sorry, I don't have an answer for the question you're asking. " . $topicsInfo . ". What else can I help you with?",
                            "I apologize, but I couldn't find specific information about that in my knowledge base. " . $topicsInfo . ". Is there anything else I can assist you with?",
                            "I'm sorry, I don't have information about that particular topic. " . $topicsInfo . ". How else can I help you today?",
                            "I apologize, but I don't have an answer for that question. " . $topicsInfo . ". What other questions do you have?"
                        ];

                        $response = $politeResponses[array_rand($politeResponses)];
                    }

                    $sources = [];
                } else {
                    Log::info("Using sources for response generation", [
                        'source_count' => $similarSources->count(),
                        'distances' => $similarSources->pluck('distance')->toArray()
                    ]);

                    // Prepare context from retrieved sources with more content
                    $baseContext = $similarSources->map(function ($source) {
                        return "Source: {$source->title}\nType: {$source->type}\nContent: " . substr($source->content, 0, 1000) . (strlen($source->content) > 1000 ? "..." : "");
                    })->toArray();

                    $baseSimilarityScore = $similarSources->first()->distance;

                    // Apply learning service to enhance context
                    $learningData = $this->learningService->generateAdaptiveResponse(
                        $chatbotId,
                        $question,
                        $baseContext,
                        1 - $baseSimilarityScore // Convert distance to similarity
                    );

                    $context = $learningData['adaptive_context'];
                    $contextUsed = $similarSources->map(function ($source) {
                        return [
                            'title' => $source->title,
                            'type' => $source->type,
                            'similarity' => 1 - $source->distance,
                        ];
                    })->toArray();

                    // Search for relevant products
                    $relevantProducts = $this->findRelevantProducts($question, $chatbotId);
                    if (!empty($relevantProducts)) {
                        $productsContext = $this->formatProductsContext($relevantProducts);

                        // Handle context as array properly - context from learning service is an array
                        if (is_array($context)) {
                            $context[] = $productsContext;
                        } else {
                            $context = $context . "\n\n" . $productsContext;
                        }

                        Log::info("Found relevant products", [
                            'product_count' => count($relevantProducts),
                            'product_names' => array_column($relevantProducts, 'name')
                        ]);
                    }

                    // Use intelligent conversation service for complex questions with RAG context
                    $systemPrompt = $this->buildSystemPrompt($chatbotId);
                    $response = $this->openAIService->generateChatResponse($systemPrompt, $question, $context);
                    $responseData = [
                        'content' => $response,
                        'usage' => [], // Usage is not tracked here for now
                        'show_contact_info' => false,
                    ];

                    // Don't include sources in response for cleaner UI
                    $sources = [];
                }
            }

            // Conversation was already created at the beginning for context

            // Create user message
            $conversation->messages()->create([
                'content' => $question,
                'is_bot' => false,
                'sources' => [],
            ]);

            // Create bot message
            $conversation->messages()->create([
                'content' => $response,
                'is_bot' => true,
                'sources' => $sources,
            ]);

            // Record interaction for learning
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            $learningData = $this->learningService->recordInteraction(
                $chatbotId,
                $conversation->id,
                $question,
                $response,
                $contextUsed ?? [],
                isset($baseSimilarityScore) ? (1 - $baseSimilarityScore) : null,
                (int) $responseTime
            );

            return [
                'reply' => $response,
                'sources' => $sources,
                'learning_data_id' => $learningData->id,
                'show_contact_info' => $responseData['show_contact_info'] ?? false,
            ];

        } catch (\Exception $e) {
            Log::error('RAG Service Error: ' . $e->getMessage());

            return [
                'reply' => 'I apologize, but I encountered an error while processing your request. Please try again later.',
                'sources' => [],
            ];
        }
    }

    protected function buildSystemPrompt(int $chatbotId = null): string
    {
        // Get dynamic company context from the chatbot's sources
        $companyContext = $this->extractCompanyContext($chatbotId);

        return "You are an intelligent AI assistant with access to a specialized knowledge base. Your goal is to provide accurate, concise answers directly from your knowledge base.

**CRITICAL RULE - USE EXACT KNOWLEDGE BASE CONTENT:**
• When the knowledge base contains specific steps, instructions, or information - USE THEM EXACTLY
• DO NOT add assumptions, extra steps, or generic advice that isn't in the knowledge base
• DO NOT expand on simple answers with your own generic knowledge
• If the knowledge base shows 4 steps, provide those 4 steps - don't add more
• Prioritize brevity and accuracy over comprehensiveness when knowledge base has concise answers
• Trust the knowledge base content - it's authoritative and complete

CONVERSATION CONTINUITY & CONTEXT AWARENESS:
• **STRICT CONTEXT MAINTENANCE**: Always maintain context from recent conversation history
• **Remember previous topics**: Build upon earlier discussions - never lose track of what was being discussed
• **Reference resolution**: When users say 'it', 'this', 'that', 'them' - they refer to the most recently mentioned product/topic
• **Follow-up handling**: For follow-up questions, continue with the SAME topic/product from previous messages
• **Topic consistency**: Do NOT switch to different products or topics unless user explicitly changes subject
• **Context over search**: Conversation context takes PRIORITY over knowledge base search when they conflict
• **Entity tracking**: Remember product names, services, and topics mentioned in recent conversation

RESPONSE STRATEGY:
1. **For greetings**: Respond warmly and introduce yourself as the knowledge base assistant
2. **For knowledge base topics**: Extract the EXACT answer from the provided context - don't elaborate or add generic steps
3. **For follow-up questions**: CRITICAL - Continue with the same topic/product from previous conversation. Do NOT introduce new products.
4. **For reference questions** ('it', 'this', 'that'): These refer to previously mentioned items - maintain the same context.
5. **For out-of-scope questions**: Provide brief, helpful general information while acknowledging limitations

FORMATTING RULES:
• Use numbered lists (1., 2., 3.) for sequential items or steps
• **Make numbered list headers bold**: Format like \"1. **Main Topic**:\" then add description
• Use bullet points (•) for feature lists or options
• Use line breaks and spacing for clarity
• Structure responses for easy scanning
• Format sub-items with dashes (-) under main points
• Example format: \"1. **Sales Pipeline**:\n- Description of the issue\n- Additional details\"
• Never mention sources or references

RESPONSE GUIDELINES:
• **When context matches**: Extract and present relevant information clearly and completely
• **When context is limited**: Provide helpful general knowledge if appropriate, then suggest they refer to specialized resources
• **Be conversational**: Write naturally, not robotically
• **Stay focused**: Answer the specific question asked
• **Be concise but complete**: Provide enough detail to be useful without overwhelming

HANDLING OUT-OF-SCOPE QUESTIONS:
Instead of saying \"I don't have information,\" provide brief helpful context about the topic if it's general knowledge, then acknowledge your specialization. For example:
- Technical questions: Give basic definition + suggest official documentation
- General topics: Provide brief helpful overview + note your knowledge base focus
- Completely unrelated: Politely redirect to your specialized knowledge areas

PRODUCT RECOMMENDATIONS:
• **Prioritize your knowledge base**: When users ask about products, services, or solutions, ALWAYS check the knowledge base first for relevant content
• **Knowledge base first approach**: Recommend products and solutions from your knowledge base before mentioning any external alternatives
• **Use your sources**: Information from your knowledge base (videos, documentation, product pages, training data) is your primary source
• **Include details**: Mention key features, benefits, pricing, and use cases when recommending products from your knowledge base
• **Natural integration**: Present solutions as helpful recommendations without being overly sales-focused
• **Comprehensive recommendations**: When appropriate, suggest multiple relevant items from your knowledge base
• **Focus on your content**: Prioritize information from your knowledge base over general recommendations

{$companyContext}

The context from your knowledge base follows. Use it as your primary source of truth, especially for product recommendations and technical solutions.";
    }

    /**
     * Extract company context from chatbot sources dynamically
     */
    protected function extractCompanyContext(int $chatbotId = null): string
    {
        if (!$chatbotId) {
            return "KNOWLEDGE BASE CONTEXT:\n• You have access to a curated knowledge base with specialized information\n• Always prioritize information from your knowledge base over general knowledge\n• Your knowledge base contains the most accurate and up-to-date information for your domain";
        }

        try {
            // Get sample of sources to understand the company/domain
            $sources = \App\Models\Source::where('chatbot_id', $chatbotId)
                ->where('status', 'completed')
                ->whereNotNull('metadata')
                ->limit(10)
                ->get();

            $productCount = 0;
            $serviceCount = 0;
            $domains = [];

            foreach ($sources as $source) {
                $metadata = $source->metadata ?? [];

                if (isset($metadata['content_type'])) {
                    if ($metadata['content_type'] === 'product') $productCount++;
                    if ($metadata['content_type'] === 'service') $serviceCount++;
                }

                if ($source->url) {
                    $parsed = parse_url($source->url);
                    if (isset($parsed['host'])) {
                        $domain = preg_replace('/^www\./', '', strtolower($parsed['host']));
                        $domains[] = $domain;
                    }
                }
            }

            $uniqueDomains = array_unique($domains);
            $primaryDomain = !empty($uniqueDomains) ? $uniqueDomains[0] : '';

            $context = "KNOWLEDGE BASE CONTEXT:\n";
            $context .= "• Your knowledge base contains specialized information";
            if ($productCount > 0) $context .= " including {$productCount} product(s)";
            if ($serviceCount > 0) $context .= " and {$serviceCount} service(s)";
            $context .= "\n• Always prioritize information from your knowledge base over general recommendations\n";
            if ($primaryDomain) {
                $context .= "• Your knowledge base includes content from {$primaryDomain} and related sources\n";
            }
            $context .= "• When users ask about solutions, products, or services, check your knowledge base first for relevant matches";

            return $context;

        } catch (\Exception $e) {
            \Log::info('Could not extract company context: ' . $e->getMessage());
            return "KNOWLEDGE BASE CONTEXT:\n• You have access to a specialized knowledge base\n• Always prioritize information from your knowledge base over general knowledge\n• Your knowledge base contains the most relevant and accurate information for your domain";
        }
    }

    /**
     * Search for relevant products based on user query
     */
    protected function findRelevantProducts(string $question, int $chatbotId): array
    {
        try {
            // Check for specific keyword patterns and prioritize certain products
            $questionLower = strtolower($question);
            $priorityProducts = collect();

            // Get all products for this chatbot
            $allProducts = Product::where('chatbot_id', $chatbotId)
                ->where('is_active', true)
                ->get();

            // Priority matching for specific requests - Dynamic keyword matching

            // Smart keyword matching based on user products
            $priorityMatches = $this->findPriorityMatches($question, $allProducts);
            foreach ($priorityMatches as $match) {
                if (!$priorityProducts->contains('id', $match->id)) {
                    $priorityProducts->push($match);
                    Log::info("Found priority product match", [
                        'product_name' => $match->name,
                        'match_reason' => $match->match_reason,
                        'similarity_score' => $match->similarity_score
                    ]);
                }
            }

            // Check training data from chatbot_training_data table
            $trainingRecommendations = $this->getTrainingDataRecommendations($question, $chatbotId);
            foreach ($trainingRecommendations as $productName) {
                $matchedProduct = $allProducts->first(function ($product) use ($productName) {
                    return stripos($product->name, $productName) !== false;
                });

                if ($matchedProduct && !$priorityProducts->contains('id', $matchedProduct->id)) {
                    $matchedProduct->similarity_score = 0.85; // High priority from training
                    $matchedProduct->match_reason = 'Training data recommendation';
                    $priorityProducts->push($matchedProduct);
                    Log::info("Found product from training data", ['product_name' => $matchedProduct->name]);
                }
            }

            // If we have priority products, start with them
            if ($priorityProducts->isNotEmpty()) {
                Log::info("Using priority products", ['count' => $priorityProducts->count()]);
                $remainingSlots = 3 - $priorityProducts->count();

                if ($remainingSlots > 0) {
                    // Fill remaining slots with vector similarity, excluding already selected
                    $vectorProducts = $this->getVectorSimilarProducts($question, $chatbotId, $priorityProducts->pluck('id')->toArray(), $remainingSlots);
                    $priorityProducts = $priorityProducts->merge($vectorProducts);
                }

                return $this->formatProductResults($priorityProducts->take(3));
            }

            // Fall back to vector similarity search
            Log::info("Using vector similarity search");

            // Generate embedding for the user question
            $queryEmbedding = $this->openAIService->generateEmbedding($question);

            // Find similar products using vector search
            $products = Product::where('chatbot_id', $chatbotId)
                ->where('is_active', true)
                ->get()
                ->map(function ($product) use ($queryEmbedding) {
                    // Calculate similarity with description
                    $descriptionSimilarity = $product->description_embedding
                        ? $this->calculateCosineSimilarity($queryEmbedding, $product->description_embedding)
                        : 0;

                    // Calculate similarity with features
                    $featuresSimilarity = $product->features_embedding
                        ? $this->calculateCosineSimilarity($queryEmbedding, $product->features_embedding)
                        : 0;

                    // Use the best similarity score
                    $product->similarity_score = max($descriptionSimilarity, $featuresSimilarity);
                    return $product;
                })
                ->filter(function ($product) {
                    return $product->similarity_score > 0.7; // Threshold for relevance
                })
                ->sortByDesc('similarity_score')
                ->take(3) // Limit to top 3 products
                ->values();

            // Also search by keywords in the question
            $keywordProducts = Product::where('chatbot_id', $chatbotId)
                ->where('is_active', true)
                ->where(function ($query) use ($question) {
                    $words = explode(' ', strtolower($question));
                    foreach ($words as $word) {
                        if (strlen($word) > 3) {
                            $query->orWhere('name', 'ILIKE', "%{$word}%")
                                  ->orWhere('description', 'ILIKE', "%{$word}%")
                                  ->orWhereJsonContains('keywords', $word)
                                  ->orWhereJsonContains('features', $word);
                        }
                    }
                })
                ->limit(2)
                ->get();

            // Merge and deduplicate results
            $allProducts = $products->merge($keywordProducts)->unique('id');

            return $this->formatProductResults($allProducts->take(3));

        } catch (\Exception $e) {
            Log::error('Product search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training data recommendations for a question
     */
    protected function getTrainingDataRecommendations(string $question, int $chatbotId): array
    {
        try {
            // Search in chatbot_training_data table
            $trainingData = \DB::table('chatbot_training_data')
                ->where('chatbot_id', $chatbotId)
                ->get();

            $recommendations = [];
            foreach ($trainingData as $data) {
                // Simple keyword matching in training data
                if (stripos($data->question, $question) !== false ||
                    stripos($question, $data->question) !== false) {
                    // Extract product names from answers
                    $productMatches = [];
                    preg_match_all('/\b(web development|website development|software|service|product)\b/i', $data->answer, $productMatches);
                    if (!empty($productMatches[0])) {
                        $recommendations = array_merge($recommendations, $productMatches[0]);
                    }
                }
            }

            return array_unique($recommendations);
        } catch (\Exception $e) {
            Log::error('Training data search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vector similarity products excluding already selected ones
     */
    protected function getVectorSimilarProducts(string $question, int $chatbotId, array $excludeIds = [], int $limit = 3): \Illuminate\Support\Collection
    {
        try {
            $queryEmbedding = $this->openAIService->generateEmbedding($question);

            return Product::where('chatbot_id', $chatbotId)
                ->where('is_active', true)
                ->whereNotIn('id', $excludeIds)
                ->get()
                ->map(function ($product) use ($queryEmbedding) {
                    // Calculate similarity with description
                    $descriptionSimilarity = $product->description_embedding
                        ? $this->calculateCosineSimilarity($queryEmbedding, $product->description_embedding)
                        : 0;

                    // Calculate similarity with features
                    $featuresSimilarity = $product->features_embedding
                        ? $this->calculateCosineSimilarity($queryEmbedding, $product->features_embedding)
                        : 0;

                    $product->similarity_score = max($descriptionSimilarity, $featuresSimilarity);
                    return $product;
                })
                ->filter(function ($product) {
                    return $product->similarity_score > 0.6; // Lower threshold for fallback
                })
                ->sortByDesc('similarity_score')
                ->take($limit);
        } catch (\Exception $e) {
            Log::error('Vector similarity search error: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Find priority matches based on keywords and context
     */
    protected function findPriorityMatches(string $question, $allProducts): array
    {
        $matches = [];
        $questionLower = strtolower($question);

        // Define common keyword patterns and their weights
        $keywordPatterns = [
            // Technology patterns
            'ecommerce|e-commerce|online store|shop' => ['ecommerce', 'shop', 'store', 'cart', 'payment'],
            'cms|content management|blog|website builder' => ['cms', 'content', 'blog', 'builder'],
            'crm|customer relationship|lead management' => ['crm', 'customer', 'lead', 'sales'],
            'on-?demand|marketplace|multi-?vendor|platform' => ['on-demand', 'marketplace', 'platform', 'vendor'],
            'web.*development|website.*development|web.*design' => ['web', 'development', 'design', 'website'],
            'app.*development|mobile.*app|ios|android' => ['app', 'mobile', 'ios', 'android'],
            'seo|search.*optimization|ranking' => ['seo', 'search', 'optimization', 'ranking'],
            'hosting|server|domain|cloud' => ['hosting', 'server', 'domain', 'cloud'],
            'analytics|tracking|reporting|dashboard' => ['analytics', 'tracking', 'reporting', 'dashboard'],
            'marketing|advertising|promotion|campaign' => ['marketing', 'advertising', 'promotion'],
            'booking|appointment|reservation|scheduling' => ['booking', 'appointment', 'reservation', 'scheduling'],
            'learning|education|course|training|lms' => ['learning', 'education', 'course', 'training', 'lms'],
            'real.*estate|property|listing|rental' => ['real estate', 'property', 'listing', 'rental'],
            'inventory|stock|warehouse|management' => ['inventory', 'stock', 'warehouse', 'management']
        ];

        foreach ($allProducts as $product) {
            $score = 0;
            $matchedKeywords = [];
            $matchReason = '';

            // Check product name for direct keyword matches
            foreach ($keywordPatterns as $pattern => $keywords) {
                if (preg_match("/$pattern/i", $questionLower)) {
                    foreach ($keywords as $keyword) {
                        if (stripos($product->name, $keyword) !== false ||
                            stripos($product->description, $keyword) !== false) {
                            $score += 0.15;
                            $matchedKeywords[] = $keyword;
                        }
                    }
                }
            }

            // Check for direct name matches in question
            $productNameWords = explode(' ', strtolower($product->name));
            foreach ($productNameWords as $word) {
                if (strlen($word) > 3 && stripos($questionLower, $word) !== false) {
                    $score += 0.2;
                    $matchedKeywords[] = $word;
                }
            }

            // Check keywords and tags if available
            if ($product->keywords) {
                foreach ($product->keywords as $keyword) {
                    if (stripos($questionLower, strtolower($keyword)) !== false) {
                        $score += 0.1;
                        $matchedKeywords[] = $keyword;
                    }
                }
            }

            // Check use cases
            if ($product->use_cases) {
                foreach ($product->use_cases as $useCase) {
                    $useCaseWords = explode(' ', strtolower($useCase));
                    foreach ($useCaseWords as $word) {
                        if (strlen($word) > 4 && stripos($questionLower, $word) !== false) {
                            $score += 0.08;
                            $matchedKeywords[] = $word;
                        }
                    }
                }
            }

            // Check features
            if ($product->features) {
                foreach ($product->features as $feature) {
                    $featureWords = explode(' ', strtolower($feature));
                    foreach ($featureWords as $word) {
                        if (strlen($word) > 4 && stripos($questionLower, $word) !== false) {
                            $score += 0.05;
                            $matchedKeywords[] = $word;
                        }
                    }
                }
            }

            // If we have a significant match, add to priority
            if ($score >= 0.3) {
                $product->similarity_score = min(0.95, 0.7 + $score); // Cap at 0.95
                $product->match_reason = count($matchedKeywords) > 0
                    ? 'Keyword match: ' . implode(', ', array_unique(array_slice($matchedKeywords, 0, 3)))
                    : 'Content similarity match';

                $matches[] = $product;

                Log::info("Priority product match found", [
                    'product_name' => $product->name,
                    'score' => $score,
                    'keywords' => array_unique($matchedKeywords),
                    'final_similarity' => $product->similarity_score
                ]);
            }
        }

        // Sort by similarity score descending
        usort($matches, function($a, $b) {
            return $b->similarity_score <=> $a->similarity_score;
        });

        return array_slice($matches, 0, 3); // Return top 3 matches
    }

    /**
     * Format product results for response
     */
    protected function formatProductResults($products): array
    {
        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'features' => $product->features ?? [],
                'key_benefits' => $product->key_benefits ?? [],
                'use_cases' => $product->use_cases ?? [],
                'primary_url' => $product->primary_url,
                'demo_url' => $product->demo_url,
                'similarity_score' => $product->similarity_score ?? 0,
                'match_reason' => $product->match_reason ?? 'Vector similarity',
            ];
        })->toArray();
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function calculateCosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            return 0;
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Format products information for context
     */
    protected function formatProductsContext(array $products): string
    {
        if (empty($products)) {
            return '';
        }

        $context = "RELEVANT PRODUCTS/SERVICES:\n\n";

        foreach ($products as $product) {
            $context .= "**{$product['name']}** ({$product['type']})\n";
            $context .= "Description: {$product['description']}\n";

            if (!empty($product['features'])) {
                $features = is_array($product['features']) ? $product['features'] : [$product['features']];
                $context .= "Key Features: " . implode(', ', array_slice($features, 0, 5)) . "\n";
            }

            if (!empty($product['key_benefits'])) {
                $benefits = is_array($product['key_benefits']) ? $product['key_benefits'] : [$product['key_benefits']];
                $context .= "Benefits: " . implode(', ', array_slice($benefits, 0, 3)) . "\n";
            }

            if (!empty($product['use_cases'])) {
                $useCases = is_array($product['use_cases']) ? $product['use_cases'] : [$product['use_cases']];
                $context .= "Use Cases: " . implode(', ', array_slice($useCases, 0, 3)) . "\n";
            }

            if ($product['primary_url']) {
                $context .= "More Info: {$product['primary_url']}\n";
            }

            if ($product['demo_url']) {
                $context .= "Try Demo: {$product['demo_url']}\n";
            }

            $context .= "\n";
        }

        return $context;
    }

    /**
     * Check if this is a simple interaction that doesn't need RAG
     */
    private function isSimpleInteraction(string $question): bool
    {
        $lowerQuestion = strtolower(trim($question));

        // Greeting patterns
        $greetingPatterns = [
            'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
            'greetings', 'yo', 'hiya', 'howdy', 'sup', 'what\'s up', 'how are you'
        ];

        // Simple help requests
        $helpPatterns = [
            'help me', 'help', 'can you help', 'i need help', 'assist me',
            'how can you help', 'what can you do', 'what can you help with'
        ];

        // Check all patterns
        $allPatterns = array_merge($greetingPatterns, $helpPatterns);

        foreach ($allPatterns as $pattern) {
            if (str_contains($lowerQuestion, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log token usage for conversation
     */
    private function logTokenUsage(int $chatbotId, int $conversationId, array $responseData): void
    {
        if (!isset($responseData['usage'])) {
            return;
        }

        $usage = $responseData['usage'];
        $model = $responseData['model'] ?? 'gpt-4o-mini';

        $cost = TokenUsage::calculateCost(
            $usage['prompt_tokens'],
            $usage['completion_tokens'],
            $model
        );

        TokenUsage::create([
            'chatbot_id' => $chatbotId,
            'conversation_id' => $conversationId,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'cost' => $cost,
            'model' => $model,
            'service_type' => 'openai',
            'metadata' => [
                'timestamp' => now(),
                'service' => 'rag_service',
            ],
        ]);
    }

    /**
     * Detect if the current question is a follow-up to previous conversation
     */
    private function detectFollowUpQuestion(string $question, array $recentMessages): bool
    {
        $followUpPatterns = [
            '/^(tell me|explain|what about|how about|more about|details about|can you tell|can you explain)/i',
            '/^(yes,?\s*)?(sure,?\s*)?(explain|tell|show|describe)/i',
            '/\b(it|this|that|them|they)\b.*\b(work|works|feature|features|test|testing|use|using|price|cost|benefit)/i',
            '/^(how (can|do|does)|what (is|are|does)|where (can|do))/i',
            '/\b(more info|more details|additional info|further info)/i'
        ];

        foreach ($followUpPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        // Check conversation context for follow-up indicators
        if (!empty($recentMessages) && count($recentMessages) >= 2) {
            $lastMessage = end($recentMessages);
            if (str_starts_with($lastMessage, 'Assistant:') && strlen($question) < 100) {
                // Short questions after bot responses are likely follow-ups
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if the question contains references to previous context
     */
    private function detectReferences(string $question): bool
    {
        $referencePatterns = [
            '/\b(it|this|that|these|those|them|they)\b/i',
            '/\b(the (one|product|service|script|solution|platform))\b/i',
            '/\b(above|mentioned|previous|earlier|before)\b/i'
        ];

        foreach ($referencePatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enhance query with conversation context for better matching
     */
    private function enhanceQueryWithContext(string $question, array $recentMessages): string
    {
        if (empty($recentMessages)) {
            return $question;
        }

        // Extract product/service names from recent conversation
        $mentionedEntities = [];
        $lastFewMessages = array_slice($recentMessages, -4); // Look at last 4 messages

        foreach ($lastFewMessages as $message) {
            // Look for capitalized words that might be product names
            preg_match_all('/\b([A-Z][a-z]+(?:[A-Z][a-z]+)*)\b/', $message, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $entity) {
                    // Filter out common words
                    if (!in_array(strtolower($entity), ['Assistant', 'User', 'Hello', 'How', 'Can', 'Help', 'You', 'I', 'That', 'This', 'The'])) {
                        $mentionedEntities[] = $entity;
                    }
                }
            }
        }

        // If we found entities and the question has references, enhance the query
        if (!empty($mentionedEntities) && $this->detectReferences($question)) {
            $uniqueEntities = array_unique($mentionedEntities);
            $lastEntity = end($uniqueEntities); // Most recent entity mentioned

            // Add context to the query for better embedding match
            $enhancedQuery = $question . " " . $lastEntity;

            Log::info("Enhanced query for better context matching", [
                'original' => $question,
                'enhanced' => $enhancedQuery,
                'entities' => $uniqueEntities
            ]);

            return $enhancedQuery;
        }

        return $question;
    }

    /**
     * Check if the query is related to technical issues or troubleshooting
     */
    private function isTechnicalIssueQuery(string $query): bool
    {
        $lowerQuery = strtolower($query);

        $technicalKeywords = [
            'not working', 'doesn\'t work', 'not functioning', 'freezes', 'frozen', 'freeze',
            'crashed', 'error', 'bug', 'problem', 'issue', 'broken', 'failed',
            'won\'t update', 'can\'t update', 'update button', 'button freezes',
            'nothing happens', 'nothing happened', 'not responding',
            'php version', 'version compatibility', 'after upgrade', 'after update',
            'stopped working', 'clear cache', 'troubleshoot', 'fix', 'solve',
            'installation issue', 'setup problem', 'configuration error',
            // Add contact/support keywords to prioritize technical solutions
            'contact', 'support', 'help', 'assistance', 'support team',
            'get in touch', 'reach out', 'contact info', 'contact information',
            'support information', 'customer support', 'technical support'
        ];

        foreach ($technicalKeywords as $keyword) {
            if (strpos($lowerQuery, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
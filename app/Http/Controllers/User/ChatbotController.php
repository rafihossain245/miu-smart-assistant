<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceContent;
use App\Events\SourceCreated;
use App\Models\Chatbot;
use App\Models\Source;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ChatbotController extends Controller
{
    public function index(Request $request)
    {
        $chatbots = $request->user()->chatbots()
            ->withCount(['sources', 'conversations'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Chatbots/Index', [
            'chatbots' => $chatbots,
        ]);
    }

    public function create()
    {
        return Inertia::render('Chatbots/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'welcome_message' => 'required|string|max:500',
            'initial_message' => 'nullable|string|max:500',
            'appearance' => 'nullable|array',
        ]);

        $chatbot = $request->user()->chatbots()->create([
            ...$validated,
            'appearance' => $validated['appearance'] ?? [],
        ]);

        return redirect()->route('chatbots.show', $chatbot)
            ->with('message', 'Chatbot created successfully!');
    }

    public function show(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        $chatbot->load(['sources' => function ($query) {
            $query->latest();
        }]);

        $stats = [
            'total_sources' => $chatbot->sources()->count(),
            'completed_sources' => $chatbot->sources()->completed()->count(),
            'pending_sources' => $chatbot->sources()->pending()->count(),
            'failed_sources' => $chatbot->sources()->failed()->count(),
            'total_conversations' => $chatbot->conversations()->count(),
            'conversations_today' => $chatbot->conversations()->whereDate('created_at', today())->count(),
        ];

        return Inertia::render('Chatbots/Show', [
            'chatbot' => $chatbot,
            'stats' => $stats,
        ]);
    }

    public function edit(Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        return Inertia::render('Chatbots/Edit', [
            'chatbot' => $chatbot,
        ]);
    }

    public function update(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'welcome_message' => 'required|string|max:500',
            'initial_message' => 'nullable|string|max:500',
            'appearance' => 'nullable|array',
            'is_active' => 'boolean',

            // Advanced Settings
            'customer_personas' => 'nullable|array',
            'brand_voice' => 'nullable|string|max:100',
            'customer_query_examples' => 'nullable|array',
            'integrations' => 'nullable|array',
            'service_urls' => 'nullable|array',
            'custom_personas' => 'nullable|array',

            // Contact Settings
            'contact_settings' => 'nullable|array',
            'contact_settings.enabled' => 'nullable|boolean',
            'contact_settings.whatsapp_number' => 'nullable|string|max:20',
            'contact_settings.phone_number' => 'nullable|string|max:20',
            'contact_settings.email_address' => 'nullable|email|max:255',
            'contact_settings.support_email' => 'nullable|email|max:255',
            'contact_settings.support_url' => 'nullable|url|max:500',
            'contact_settings.business_hours' => 'nullable|string|max:255',
            'contact_settings.support_message' => 'nullable|string|max:1000',
        ]);

        // Handle custom personas storage
        if (isset($validated['custom_personas'])) {
            $metadata = $chatbot->metadata ?? [];
            $metadata['custom_personas'] = $validated['custom_personas'];
            $validated['metadata'] = $metadata;
        }

        $chatbot->update($validated);

        return redirect()->route('chatbots.edit', $chatbot)
            ->with('success', 'Chatbot settings updated successfully!');
    }

    public function destroy(Chatbot $chatbot)
    {
        $this->authorize('delete', $chatbot);

        $chatbot->delete();

        return redirect()->route('chatbots.index')
            ->with('message', 'Chatbot deleted successfully!');
    }

    public function sources(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        $sources = $chatbot->sources()->latest()->paginate(15);

        return Inertia::render('Chatbots/Sources', [
            'chatbot' => $chatbot,
            'sources' => $sources,
        ]);
    }

    public function test(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        return Inertia::render('Chatbots/Test', [
            'chatbot' => $chatbot,
        ]);
    }

    public function embed(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        return Inertia::render('Chatbots/Embed', [
            'chatbot' => $chatbot,
        ]);
    }

    public function analytics(Request $request, Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        $dateRange = $request->get('date_range', 7); // default to 7 days
        $dateFrom = now()->subDays((int) $dateRange);

        $conversations = $chatbot->conversations()
            ->latest()
            ->paginate(20);

        $analytics = [
            'total_conversations' => $chatbot->conversations()->count(),
            'conversations_this_week' => $chatbot->conversations()->where('created_at', '>=', now()->subWeek())->count(),
            'conversations_this_month' => $chatbot->conversations()->where('created_at', '>=', now()->subMonth())->count(),
            'avg_daily_conversations' => $chatbot->conversations()->where('created_at', '>=', now()->subDays(30))->count() / 30,
            'conversations_in_range' => $chatbot->conversations()->where('created_at', '>=', $dateFrom)->count(),
        ];

        // Get products for analytics
        $products = $chatbot->products()
            ->where('mention_count', '>', 0)
            ->orderByDesc('mention_count')
            ->get();

        // Handle AJAX requests for dynamic updates
        if ($request->expectsJson()) {
            return response()->json([
                'analytics' => $analytics,
                'products' => $products,
            ]);
        }

        return Inertia::render('Chatbots/Analytics', [
            'chatbot' => $chatbot,
            'conversations' => $conversations,
            'analytics' => $analytics,
            'products' => $products,
        ]);
    }

    public function storeSources(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'type' => 'required|in:url,pdf,youtube,text,sitemap,youtube_playlist,technical_issue',
            'title' => 'required|string|max:255',
            'url' => 'required_unless:type,text,pdf,technical_issue|nullable|string',
            'content' => 'required_if:type,text,technical_issue|nullable|string',
            'file' => 'required_if:type,pdf|nullable|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $sourceData = [
            'chatbot_id' => $chatbot->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'status' => 'pending',
        ];

        if ($validated['type'] === 'pdf' && $request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('pdfs', 'local');
            $sourceData['url'] = $path;
            $sourceData['content'] = ''; // Will be extracted by job
        } elseif (in_array($validated['type'], ['text', 'technical_issue'])) {
            $sourceData['content'] = $validated['content'];
        } else {
            $sourceData['url'] = $validated['url'];
            $sourceData['content'] = ''; // Will be extracted by job
        }

        $source = Source::create($sourceData);

        // Fire source created event for real-time updates
        event(new SourceCreated($source));

        // Dispatch job to process the source
        ProcessSourceContent::dispatch($source);

        return redirect()->route('chatbots.sources', $chatbot)
            ->with('message', 'Source added successfully and is being processed!');
    }

    public function syncSource(Request $request, Chatbot $chatbot, Source $source)
    {
        $this->authorize('update', $chatbot);

        // Re-dispatch the job to process the source
        ProcessSourceContent::dispatch($source);

        return back()->with('message', 'Source sync initiated successfully!');
    }

    public function syncAllSources(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        // Get all sources for this chatbot
        $sources = $chatbot->sources;

        foreach ($sources as $source) {
            ProcessSourceContent::dispatch($source);
        }

        return back()->with('message', "Sync initiated for all {$sources->count()} sources!");
    }

    public function conversations(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        // Get all conversations with messages
        $conversations = $chatbot->conversations()
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->latest()
            ->paginate(20);

        return Inertia::render('Chatbots/Conversations', [
            'chatbot' => $chatbot,
            'conversations' => $conversations,
        ]);
    }

    public function products(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        $products = $chatbot->products()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->paginate(20);

        return Inertia::render('Chatbots/Products', [
            'chatbot' => $chatbot,
            'products' => $products,
        ]);
    }

    public function storeProduct(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:product,service',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'pricing' => 'nullable|array',
            'features' => 'nullable|array',
            'specifications' => 'nullable|array',
            'primary_url' => 'nullable|url',
            'demo_url' => 'nullable|url',
            'documentation_url' => 'nullable|url',
            'additional_urls' => 'nullable|array',
            'image_url' => 'nullable|url',
            'gallery_urls' => 'nullable|array',
            'video_url' => 'nullable|url',
            'target_audience' => 'nullable|array',
            'use_cases' => 'nullable|array',
            'industries' => 'nullable|array',
            'ai_summary' => 'nullable|string',
            'common_questions' => 'nullable|array',
            'key_benefits' => 'nullable|array',
            'competitors' => 'nullable|array',
            'keywords' => 'nullable|array',
            'meta_description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $validated['chatbot_id'] = $chatbot->id;

        // Generate embeddings if OpenAI service is available
        try {
            $openAIService = app(\App\Services\OpenAIService::class);
            $validated['description_embedding'] = $openAIService->generateEmbedding($validated['description']);

            if (!empty($validated['features'])) {
                $featuresText = implode(' ', $validated['features']);
                $validated['features_embedding'] = $openAIService->generateEmbedding($featuresText);
            }
        } catch (\Exception $e) {
            // Continue without embeddings if OpenAI fails
        }

        $product = Product::create($validated);

        return redirect()->route('chatbots.products', $chatbot)
            ->with('success', 'Product/Service created successfully!');
    }

    public function updateProduct(Request $request, Chatbot $chatbot, Product $product)
    {
        $this->authorize('update', $chatbot);

        if ($product->chatbot_id !== $chatbot->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:product,service',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'pricing' => 'nullable|array',
            'features' => 'nullable|array',
            'specifications' => 'nullable|array',
            'primary_url' => 'nullable|url',
            'demo_url' => 'nullable|url',
            'documentation_url' => 'nullable|url',
            'additional_urls' => 'nullable|array',
            'image_url' => 'nullable|url',
            'gallery_urls' => 'nullable|array',
            'video_url' => 'nullable|url',
            'target_audience' => 'nullable|array',
            'use_cases' => 'nullable|array',
            'industries' => 'nullable|array',
            'ai_summary' => 'nullable|string',
            'common_questions' => 'nullable|array',
            'key_benefits' => 'nullable|array',
            'competitors' => 'nullable|array',
            'keywords' => 'nullable|array',
            'meta_description' => 'nullable|string',
            'tags' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Update embeddings if description or features changed
        try {
            $openAIService = app(\App\Services\OpenAIService::class);

            if ($product->description !== $validated['description']) {
                $validated['description_embedding'] = $openAIService->generateEmbedding($validated['description']);
            }

            if (!empty($validated['features']) && $product->features !== $validated['features']) {
                $featuresText = implode(' ', $validated['features']);
                $validated['features_embedding'] = $openAIService->generateEmbedding($featuresText);
            }
        } catch (\Exception $e) {
            // Continue without updating embeddings if OpenAI fails
        }

        $product->update($validated);

        return redirect()->route('chatbots.products', $chatbot)
            ->with('success', 'Product/Service updated successfully!');
    }

    public function destroyProduct(Chatbot $chatbot, Product $product)
    {
        $this->authorize('update', $chatbot);

        if ($product->chatbot_id !== $chatbot->id) {
            abort(403);
        }

        $product->delete();

        return redirect()->route('chatbots.products', $chatbot)
            ->with('success', 'Product/Service deleted successfully!');
    }

    public function extractProductInfo(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'input' => 'required|string|max:2000',
        ]);

        try {
            $extractionService = app(\App\Services\ProductExtractionService::class);
            $result = $extractionService->extractProductInfo($validated['input']);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to extract product information: ' . $e->getMessage()
            ], 500);
        }
    }

    public function feedQueryExamplesToAI(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'query_examples' => 'required|array',
            'query_examples.*.question' => 'required|string|max:500',
            'query_examples.*.answer' => 'required|string|max:1000',
        ]);

        try {
            $openAIService = app(\App\Services\OpenAIService::class);

            // Generate a comprehensive training summary for the AI
            $trainingPrompt = "Analyze these customer question-answer pairs and generate training insights:\n\n";

            foreach ($validated['query_examples'] as $index => $example) {
                $trainingPrompt .= "Q" . ($index + 1) . ": " . $example['question'] . "\n";
                $trainingPrompt .= "A" . ($index + 1) . ": " . $example['answer'] . "\n\n";
            }

            $trainingPrompt .= "\nGenerate insights about common customer concerns, preferred response patterns, and key information that should be emphasized in future responses.";

            // Get AI insights about the training data
            $aiInsights = $openAIService->generateText($trainingPrompt, 500);

            // Update chatbot's AI summary with new training data and insights
            $this->updateChatbotAISummary($chatbot, $validated['query_examples'], $aiInsights);

            // Also update the chatbot's customer_query_examples field to ensure they're saved
            $existingExamples = $chatbot->customer_query_examples ?? [];
            $newExamples = array_merge($existingExamples, $validated['query_examples']);

            // Remove duplicates based on question similarity
            $uniqueExamples = [];
            foreach ($newExamples as $example) {
                $isDuplicate = false;
                foreach ($uniqueExamples as $existing) {
                    if (similar_text(strtolower($example['question']), strtolower($existing['question'])) > 80) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if (!$isDuplicate) {
                    $uniqueExamples[] = $example;
                }
            }

            $chatbot->update(['customer_query_examples' => $uniqueExamples]);
            $this->syncResponseTrainingData($chatbot, $uniqueExamples);

            return response()->json([
                'success' => true,
                'message' => 'Response training saved and indexed for assistant answers.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process training data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateChatbotAISummary(Chatbot $chatbot, array $queryExamples, string $aiInsights = '')
    {
        $summary = "Response Training Summary:\n";
        foreach ($queryExamples as $index => $example) {
            $summary .= "Q" . ($index + 1) . ": " . $example['question'] . "\n";
            $summary .= "A" . ($index + 1) . ": " . substr($example['answer'], 0, 100) . "...\n\n";
        }

        if (!empty($aiInsights)) {
            $summary .= "\nAI Training Insights:\n" . $aiInsights;
        }

        // Store the summary in chatbot metadata for future reference
        $metadata = $chatbot->metadata ?? [];
        $metadata['ai_training_summary'] = $summary;
        $metadata['last_training_update'] = now()->toISOString();
        $metadata['training_examples_count'] = count($queryExamples);

        $chatbot->update(['metadata' => $metadata]);
    }

    private function syncResponseTrainingData(Chatbot $chatbot, array $queryExamples): void
    {
        $normalizedQuestions = [];

        foreach ($queryExamples as $example) {
            $question = trim($example['question'] ?? '');
            $answer = trim($example['answer'] ?? '');

            if ($question === '' || $answer === '') {
                continue;
            }

            $normalizedQuestions[] = $question;

            DB::table('chatbot_training_data')->updateOrInsert(
                [
                    'chatbot_id' => $chatbot->id,
                    'question' => $question,
                ],
                [
                    'answer' => $answer,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('chatbot_training_data')
            ->where('chatbot_id', $chatbot->id)
            ->when(!empty($normalizedQuestions), fn($query) => $query->whereNotIn('question', $normalizedQuestions))
            ->when(empty($normalizedQuestions), fn($query) => $query)
            ->delete();
    }

    public function training(Chatbot $chatbot)
    {
        $this->authorize('view', $chatbot);

        // Get existing query examples from customer_query_examples field
        $queryExamples = $chatbot->customer_query_examples ?? [];

        return Inertia::render('Chatbots/Training', [
            'chatbot' => $chatbot,
            'queryExamples' => $queryExamples,
        ]);
    }

    public function storeTraining(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'customer_query_examples' => 'required|array',
            'customer_query_examples.*.question' => 'required|string|max:500',
            'customer_query_examples.*.answer' => 'required|string|max:1000',
        ]);

        // Update chatbot with new customer query examples
        $chatbot->update([
            'customer_query_examples' => $validated['customer_query_examples']
        ]);
        $this->syncResponseTrainingData($chatbot, $validated['customer_query_examples']);

        return back()->with('message', 'Response training saved successfully!');
    }

    public function importConversations(Request $request, Chatbot $chatbot)
    {
        $this->authorize('update', $chatbot);

        $validated = $request->validate([
            'conversation_text' => 'nullable|string|max:10000',
            'conversation_file' => 'nullable|file|mimes:jpeg,png,gif,webp,txt|max:10240', // 10MB max
        ]);

        try {
            $conversationContent = '';

            // Process file upload if provided
            if ($request->hasFile('conversation_file')) {
                $file = $request->file('conversation_file');

                if (in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    // Use OpenAI Vision API to extract text from image
                    $openAIService = app(\App\Services\OpenAIService::class);
                    $conversationContent = $openAIService->extractTextFromImage($file);
                } else {
                    // Read text file
                    $conversationContent = file_get_contents($file->getPathname());
                }
            }

            // Use provided text if no file or as fallback
            if (empty($conversationContent) && !empty($validated['conversation_text'])) {
                $conversationContent = $validated['conversation_text'];
            }

            if (empty($conversationContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No conversation content found to process.'
                ], 400);
            }

            // Extract Q&A pairs from conversation content
            $extractedExamples = $this->extractQAFromConversation($conversationContent);

            if (empty($extractedExamples)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No question-answer pairs could be extracted from the conversation.'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => count($extractedExamples) . ' conversation pairs extracted successfully!',
                'extracted_examples' => $extractedExamples
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process conversation: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractQAFromConversation(string $conversationText): array
    {
        try {
            $openAIService = app(\App\Services\OpenAIService::class);

            $prompt = "Extract question-answer pairs from this conversation.
            Look for patterns where customers ask questions and support/business responds.
            Return ONLY a JSON array of objects with 'question' and 'answer' fields.
            Focus on meaningful exchanges that would be useful for training a customer service AI.

            Conversation:
            " . $conversationText;

            $response = $openAIService->generateText($prompt);

            // Try to parse JSON response
            $decoded = json_decode($response, true);

            if (is_array($decoded)) {
                // Validate each item has question and answer
                return array_filter($decoded, function($item) {
                    return is_array($item) &&
                           isset($item['question']) &&
                           isset($item['answer']) &&
                           !empty(trim($item['question'])) &&
                           !empty(trim($item['answer']));
                });
            }

            return [];

        } catch (\Exception $e) {
            // Fallback: simple regex-based extraction
            return $this->simpleQAExtraction($conversationText);
        }
    }

    private function simpleQAExtraction(string $conversationText): array
    {
        $examples = [];
        $lines = explode("\n", $conversationText);
        $currentQuestion = '';
        $currentAnswer = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Look for customer/user questions
            if (preg_match('/^(customer|user|client):\s*(.+)/i', $line, $matches)) {
                // Save previous Q&A if exists
                if (!empty($currentQuestion) && !empty($currentAnswer)) {
                    $examples[] = [
                        'question' => $currentQuestion,
                        'answer' => $currentAnswer
                    ];
                }

                $currentQuestion = trim($matches[2]);
                $currentAnswer = '';
            }
            // Look for support/agent responses
            elseif (preg_match('/^(support|agent|rep|admin):\s*(.+)/i', $line, $matches)) {
                $currentAnswer = trim($matches[2]);
            }
            // Continue building answer if no prefix
            elseif (!empty($currentQuestion) && !preg_match('/^[a-zA-Z]+:\s*/', $line)) {
                if (!empty($currentAnswer)) {
                    $currentAnswer .= ' ' . $line;
                } else {
                    $currentAnswer = $line;
                }
            }
        }

        // Add final Q&A if exists
        if (!empty($currentQuestion) && !empty($currentAnswer)) {
            $examples[] = [
                'question' => $currentQuestion,
                'answer' => $currentAnswer
            ];
        }

        return $examples;
    }

    /**
     * Display the chatbot embed view (public)
     */
    public function embedView(Request $request, Chatbot $chatbot)
    {
        // This is a public route, so we need to check if chatbot is active
        if (!$chatbot->is_active) {
            abort(404, 'Chatbot not found or inactive');
        }

        // Get tenant_id from request if provided
        $tenantId = $request->get('tenant_id');

        return Inertia::render('Chatbots/EmbedView', [
            'chatbot' => $chatbot,
            'tenant_id' => $tenantId,
        ]);
    }
}

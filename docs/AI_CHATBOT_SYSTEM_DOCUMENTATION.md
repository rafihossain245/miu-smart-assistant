# AI Chatbot System - Complete Technical Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture Components](#architecture-components)
3. [Source Data Processing Pipeline](#source-data-processing-pipeline)
4. [RAG (Retrieval-Augmented Generation) System](#rag-system)
5. [Embedding and Vector Storage](#embedding-and-vector-storage)
6. [AI Response Generation](#ai-response-generation)
7. [Adaptive Learning System](#adaptive-learning-system)
8. [Database Schema](#database-schema)
9. [API Endpoints](#api-endpoints)
10. [Configuration and Setup](#configuration-and-setup)

## System Overview

The AI Chatbot System is a comprehensive Laravel-based application that creates intelligent, context-aware chatbots using Retrieval-Augmented Generation (RAG) technology. The system processes various content sources, creates vector embeddings, and uses OpenAI's GPT models to generate contextually relevant responses while continuously learning from user interactions.

### Key Features
- **Multi-source Knowledge Base**: URLs, PDFs, YouTube videos, direct text
- **Vector-based Similarity Search**: Using PostgreSQL with pgvector extension
- **Adaptive Learning**: Self-improving responses based on user feedback
- **Real-time Processing**: Background job processing for source content
- **User Feedback Loop**: Thumbs up/down and correction mechanisms
- **Analytics and Insights**: Performance tracking and pattern analysis

## Architecture Components

### Backend Services
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   RAG Service   │    │ Learning Service│    │OpenAI Service   │
│                 │    │                 │    │                 │
│ - Query Processing │  │ - Pattern Analysis│  │ - Embeddings    │
│ - Context Building │  │ - Feedback Storage│  │ - Chat Completion│
│ - Response Gen     │  │ - Adaptive Context│  │ - Text Processing│
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
┌─────────────────────────────────▼─────────────────────────────────┐
│                        Database Layer                              │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐  │
│  │   Sources   │ │Conversations│ │   Messages  │ │  Learning   │  │
│  │             │ │             │ │             │ │    Data     │  │
│  │ + content   │ │ + session   │ │ + content   │ │ + patterns  │  │
│  │ + embedding │ │ + ip_addr   │ │ + is_bot    │ │ + feedback  │  │
│  │ + status    │ │             │ │ + sources   │ │ + metrics   │  │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘  │
└───────────────────────────────────────────────────────────────────┘
```

### Frontend Components
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Chat Interface│    │  Source Manager │    │   Analytics     │
│                 │    │                 │    │                 │
│ - Message Display│    │ - Add Sources   │    │ - Performance   │
│ - Feedback Buttons│   │ - Sync Controls │    │ - Learning Data │
│ - Real-time Updates│  │ - Status Monitor│    │ - Pattern Insights│
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Source Data Processing Pipeline

### 1. Content Ingestion Flow
```
User Adds Source → Validation → Storage → Background Processing → Embedding Generation → Status Update
```

### 2. Source Types and Processing

#### URL Sources
```php
// ContentExtractionService::extractFromUrl()
1. HTTP Request with User-Agent headers
2. HTML parsing and cleaning
3. Content extraction (article, main, content divs)
4. Text cleaning and normalization
5. Title extraction from <title> tags
```

#### PDF Sources
```php
// ContentExtractionService::extractFromPdf()
1. File upload and local storage
2. PDF parsing using Smalot\PdfParser
3. Text extraction and metadata retrieval
4. Content cleaning and formatting
```

#### YouTube Sources
```php
// ContentExtractionService::extractFromYoutube()
1. Video ID extraction from URL
2. Video metadata retrieval
3. Title and description extraction
4. Placeholder for transcript integration
```

#### Text Sources
```php
// Direct text input - no processing required
1. Immediate storage
2. Direct embedding generation
```

### 3. Background Processing Job
```php
// ProcessSourceContent Job
class ProcessSourceContent implements ShouldQueue
{
    public function handle(ContentExtractionService $contentService, OpenAIService $openAIService): void
    {
        // 1. Mark source as 'processing'
        $this->source->update(['status' => 'processing']);

        // 2. Extract content based on type
        $extractedData = $contentService->extractFromX($this->source);

        // 3. Chunk content for optimal embedding
        $chunks = $openAIService->chunkText($extractedData['content'], 1000);

        // 4. Generate embedding for the content
        $embedding = $openAIService->generateEmbedding($chunks[0]);

        // 5. Update source with embedding and mark as 'completed'
        $this->source->update([
            'embedding' => $embedding,
            'status' => 'completed',
            'content' => $extractedData['content'],
            'title' => $extractedData['title']
        ]);
    }
}
```

## RAG System

### 1. Query Processing Flow
```
User Query → Greeting Check → Embedding Generation → Similarity Search → Context Building → AI Response → Learning Record
```

### 2. Similarity Search Algorithm
```php
// Source::findSimilar() method
public static function findSimilar(array $queryEmbedding, int $chatbotId, int $limit = 5): Collection
{
    $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

    return DB::table('sources')
        ->select(['id', 'title', 'content', 'url', 'type'])
        ->selectRaw('(embedding <=> ?) as distance', [$embeddingString])  // Cosine distance
        ->where('chatbot_id', $chatbotId)
        ->where('status', 'completed')
        ->whereNotNull('embedding')
        ->orderBy('distance')  // Closest first
        ->limit($limit)
        ->get();
}
```

### 3. Context Building Process
```php
// RAGService::generateResponse()
1. Generate query embedding: $queryEmbedding = $this->openAIService->generateEmbedding($question);

2. Find similar sources: $similarSources = Source::findSimilar($queryEmbedding, $chatbotId, 5);

3. Check relevance threshold: if ($similarSources->first()->distance > 0.8) // Not relevant

4. Build base context:
   $context = $similarSources->map(function ($source) {
       return "Source: {$source->title}\nType: {$source->type}\nContent: " . substr($source->content, 0, 1000);
   })->toArray();

5. Apply adaptive learning:
   $learningData = $this->learningService->generateAdaptiveResponse($chatbotId, $question, $context, $similarity);
   $enhancedContext = $learningData['adaptive_context'];
```

## Embedding and Vector Storage

### 1. Embedding Generation
```php
// OpenAIService::generateEmbedding()
public function generateEmbedding(string $text): array
{
    $client = OpenAI::client(config('services.openai.api_key'));

    $response = $client->embeddings()->create([
        'model' => 'text-embedding-3-small',  // 1536 dimensions
        'input' => $text,
    ]);

    return $response->embeddings[0]->embedding;  // Array of 1536 float values
}
```

### 2. Vector Storage in PostgreSQL
```sql
-- Database setup with pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Sources table with vector column
CREATE TABLE sources (
    id BIGSERIAL PRIMARY KEY,
    chatbot_id BIGINT NOT NULL,
    content TEXT,
    embedding vector(1536),  -- OpenAI ada-002/3-small dimension
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Index for cosine similarity search
CREATE INDEX ON sources USING ivfflat (embedding vector_cosine_ops);
```

### 3. Similarity Search Mathematics
```
Cosine Distance Formula: 1 - (A · B) / (|A| × |B|)

Where:
- A = Query embedding vector
- B = Source embedding vector
- A · B = Dot product of vectors
- |A|, |B| = Magnitudes of vectors

Distance Range: 0 (identical) to 2 (opposite)
Similarity Threshold: < 0.8 for relevant content
```

## AI Response Generation

### 1. System Prompt Structure
```php
protected function buildSystemPrompt(): string
{
    return "You are an intelligent AI assistant with access to a specialized knowledge base.

RESPONSE STRATEGY:
1. **For greetings**: Respond warmly and introduce yourself
2. **For knowledge base topics**: Use provided context comprehensively
3. **For out-of-scope questions**: Provide brief helpful general information

FORMATTING RULES:
• Use numbered lists (1., 2., 3.) for sequential items
• **Make numbered list headers bold**: Format like '1. **Main Topic**:'
• Use bullet points (•) for feature lists
• Structure responses for easy scanning
• Never mention sources or references

HANDLING OUT-OF-SCOPE QUESTIONS:
Instead of saying 'I don't have information,' provide brief helpful context about the topic if it's general knowledge, then acknowledge your specialization.

The context from your knowledge base follows. Use it as your primary source, supplementing with appropriate general knowledge only when helpful."
}
```

### 2. Response Generation Process
```php
// RAGService::generateResponse()
1. System Prompt Building:
   $systemPrompt = $this->buildSystemPrompt();

2. Context Preparation:
   $messages = [
       ['role' => 'system', 'content' => $systemPrompt],
       ['role' => 'system', 'content' => "Context: " . implode("\n\n", $context)],
       ['role' => 'user', 'content' => $userQuestion]
   ];

3. OpenAI API Call:
   $response = $client->chat()->create([
       'model' => 'gpt-4o-mini',
       'messages' => $messages,
       'max_tokens' => 1000,
       'temperature' => 0.7,
   ]);

4. Response Extraction:
   return $response->choices[0]->message->content;
```

### 3. Response Enhancement with Learning
```php
// Learning-enhanced context
if ($learningData['learning_applied']) {
    $systemPrompt .= "\n\nNOTE: This response has been enhanced with learning from past interactions.";
    $context = $learningData['adaptive_context'];  // Includes past successful responses
}
```

## Adaptive Learning System

### 1. Learning Data Collection
```php
// Every interaction is recorded
$learningData = ChatbotLearningData::create([
    'chatbot_id' => $chatbotId,
    'conversation_id' => $conversationId,
    'user_query' => $question,
    'bot_response' => $response,
    'context_used' => $contextUsed,
    'similarity_score' => $similarityScore,
    'response_time_ms' => $responseTime,
    'query_patterns' => $extractedPatterns,  // Question types, intents, topics
    'interaction_type' => 'question',
]);
```

### 2. Pattern Extraction
```php
// LearningService::extractQueryPatterns()
protected function extractQueryPatterns(string $query): array
{
    $patterns = [];

    // Question types
    $questionWords = ['what', 'how', 'why', 'when', 'where', 'who', 'which'];
    foreach ($questionWords as $word) {
        if (stripos($query, $word) !== false) {
            $patterns['question_types'][] = $word;
        }
    }

    // Intent extraction
    if (preg_match('/\b(show|list|find|get|tell|explain|describe)\b/i', $query, $matches)) {
        $patterns['intent'] = strtolower($matches[1]);
    }

    // Topic extraction
    $commonTopics = ['ecommerce', 'script', 'php', 'laravel', 'crm', 'alternative'];
    foreach ($commonTopics as $topic) {
        if (stripos($query, $topic) !== false) {
            $patterns['topics'][] = $topic;
        }
    }

    return $patterns;
}
```

### 3. Adaptive Response Enhancement
```php
// LearningService::generateAdaptiveResponse()
public function generateAdaptiveResponse(int $chatbotId, string $query, array $baseContext, float $baseSimilarityScore): array
{
    // 1. Find similar past successful interactions
    $similarInteractions = $this->getSimilarInteractions($chatbotId, $query, 3);

    // 2. Enhance context with past successes
    $adaptiveContext = $baseContext;
    foreach ($similarInteractions as $similar) {
        $adaptiveContext[] = "Past successful response: " . $similar['interaction']->bot_response;
    }

    // 3. Apply user corrections
    $corrections = ChatbotLearningData::forChatbot($chatbotId)
        ->whereNotNull('user_correction')
        ->get();

    foreach ($corrections as $correction) {
        $similarity = $this->calculateCosineSimilarity($queryEmbedding, $correctionEmbedding);
        if ($similarity > 0.6) {
            $adaptiveContext[] = "User correction: " . $correction->user_correction;
        }
    }

    return [
        'adaptive_context' => $adaptiveContext,
        'confidence_score' => min(1.0, $baseSimilarityScore + $confidenceBoost),
        'learning_applied' => !empty($similarInteractions),
    ];
}
```

### 4. User Feedback Integration
```php
// Feedback recording
public function recordFeedback(int $learningDataId, bool $wasHelpful, array $additionalFeedback = []): void
{
    ChatbotLearningData::where('id', $learningDataId)->update([
        'was_helpful' => $wasHelpful,
        'user_feedback' => $additionalFeedback,
    ]);
}

// Correction handling with auto-source creation
public function recordCorrection(int $learningDataId, string $userCorrection): void
{
    $learningData = ChatbotLearningData::find($learningDataId);
    $learningData->update([
        'user_correction' => $userCorrection,
        'interaction_type' => 'correction',
    ]);

    // Auto-create source if correction is substantial (>100 chars)
    if (strlen($userCorrection) > 100) {
        $embedding = $this->openAIService->generateEmbedding($userCorrection);
        Source::create([
            'chatbot_id' => $learningData->chatbot_id,
            'type' => 'text',
            'title' => 'User Correction: ' . substr($learningData->user_query, 0, 50),
            'content' => $userCorrection,
            'embedding' => $embedding,
            'status' => 'completed',
        ]);
    }
}
```

## Database Schema

### Core Tables
```sql
-- Chatbots
CREATE TABLE chatbots (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    welcome_message TEXT,
    appearance JSONB,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Sources (Knowledge Base)
CREATE TABLE sources (
    id BIGSERIAL PRIMARY KEY,
    chatbot_id BIGINT NOT NULL,
    type VARCHAR(20) NOT NULL,  -- 'url', 'pdf', 'youtube', 'text'
    title VARCHAR(255) NOT NULL,
    url VARCHAR(2048),
    content TEXT,
    embedding vector(1536),
    status VARCHAR(20) DEFAULT 'pending',  -- 'pending', 'processing', 'completed', 'failed'
    error_message TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
);

-- Conversations
CREATE TABLE conversations (
    id BIGSERIAL PRIMARY KEY,
    chatbot_id BIGINT NOT NULL,
    session_id VARCHAR(255),
    ip_address VARCHAR(45),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE
);

-- Messages
CREATE TABLE messages (
    id BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL,
    content TEXT NOT NULL,
    is_bot BOOLEAN NOT NULL DEFAULT false,
    sources JSONB,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

-- Learning Data
CREATE TABLE chatbot_learning_data (
    id BIGSERIAL PRIMARY KEY,
    chatbot_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    user_query TEXT NOT NULL,
    bot_response TEXT NOT NULL,
    context_used JSONB,
    user_feedback JSONB,
    user_correction TEXT,
    similarity_score FLOAT,
    response_time_ms INTEGER,
    query_patterns JSONB,
    behavior_data JSONB,
    interaction_type VARCHAR(20) DEFAULT 'question',
    was_helpful BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (chatbot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);
```

### Indexes for Performance
```sql
-- Vector similarity search
CREATE INDEX sources_embedding_idx ON sources USING ivfflat (embedding vector_cosine_ops);

-- Quick lookups
CREATE INDEX sources_chatbot_status_idx ON sources (chatbot_id, status);
CREATE INDEX learning_data_chatbot_created_idx ON chatbot_learning_data (chatbot_id, created_at);
CREATE INDEX learning_data_helpful_idx ON chatbot_learning_data (was_helpful);
```

## API Endpoints

### Chat API
```php
// POST /api/chat
{
    "message": "User question",
    "chatbot_id": 1,
    "session_id": "unique_session_id"
}

// Response
{
    "reply": "AI generated response",
    "sources": [],
    "learning_data_id": 123
}
```

### Feedback API
```php
// POST /api/feedback
{
    "learning_data_id": 123,
    "was_helpful": true,
    "additional_feedback": {}
}

// POST /api/correction
{
    "learning_data_id": 123,
    "correction": "Better answer provided by user"
}
```

### Source Management
```php
// POST /chatbots/{id}/sources
{
    "type": "url|pdf|youtube|text",
    "title": "Source title",
    "url": "https://example.com",
    "content": "Direct text content"
}

// POST /chatbots/{id}/sources/{source}/sync
// POST /chatbots/{id}/sources/sync-all
```

## Configuration and Setup

### Environment Variables
```env
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Database with pgvector
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aibot
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue Configuration
QUEUE_CONNECTION=database
```

### Required Dependencies
```json
{
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "openai-php/laravel": "^0.8.1",
    "smalot/pdfparser": "^2.7",
    "inertiajs/inertia-laravel": "^1.0",
    "react-markdown": "^9.0.0"
}
```

### Setup Commands
```bash
# Install dependencies
composer install
npm install

# Setup database
php artisan migrate
php artisan db:seed

# Install pgvector extension
psql -d aibot -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Start queue worker
php artisan queue:work

# Build frontend
npm run build
```

## Performance Considerations

### Vector Search Optimization
- **Index Type**: IVFFlat index for cosine similarity
- **List Size**: Optimal for dataset size (default: 100 lists per 1M vectors)
- **Probe Setting**: Balance between speed and accuracy

### Embedding Caching
- **Model**: text-embedding-3-small (1536 dimensions, cost-effective)
- **Chunking**: 1000 characters per chunk for optimal context
- **Batch Processing**: Process multiple sources concurrently

### Learning Data Management
- **Retention**: 30-day sliding window for active learning
- **Aggregation**: Weekly pattern analysis for insights
- **Cleanup**: Archive old learning data to maintain performance

## Monitoring and Analytics

### Key Metrics
- **Response Accuracy**: Helpful vs unhelpful feedback ratio
- **Learning Effectiveness**: Improvement in similar query responses
- **Processing Performance**: Source processing and embedding times
- **User Engagement**: Conversation length and interaction patterns

### Dashboard Components
- **Real-time Chat Interface**: Live chatbot testing
- **Source Management**: Upload, sync, and monitor knowledge base
- **Analytics Dashboard**: Performance metrics and learning insights
- **Conversation History**: Complete interaction logs with feedback

This documentation provides a complete technical overview of the AI chatbot system, from data ingestion to intelligent response generation with continuous learning capabilities.
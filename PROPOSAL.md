# AI-Powered Chatbot System with RAG and Adaptive Learning

## Project Title
AI-Powered Chatbot System with RAG and Adaptive Learning

## Abstract
An AI-driven chatbot platform built with Laravel 12 and a React + Inertia frontend that provides highly accurate, context-aware answers using Retrieval-Augmented Generation (RAG). It ingests multi-format knowledge sources (URLs, PDFs, YouTube), creates vector embeddings (OpenAI text-embedding-3-small), stores them in PostgreSQL with pgvector, and returns concise, knowledge-base-exact responses. The system includes adaptive learning from user feedback, product recommendation integration, real-time source updates, and secure authenticated APIs for embedded chat.

## Objectives
- Deliver a production-capable RAG chatbot that answers from a curated knowledge base.
- Support multi-source ingestion and automated embedding generation.
- Ensure responses strictly follow knowledge-base content when available.
- Implement adaptive learning to improve future answers using user feedback.
- Provide a developer-friendly API and embeddable widget for external sites.

## Key Features
- RAG pipeline: content ingestion → chunking → embedding → vector storage → similarity search.
- OpenAI integration: embeddings and chat completions (configurable model via env).
- Knowledge sources: URLs, PDFs, YouTube, direct text — processed by background jobs.
- Vector storage: PostgreSQL + pgvector, ivfflat index for fast similarity search.
- Conversation continuity: context-aware replies using recent message history.
- Adaptive learning: record interactions, extract patterns, and improve recommendations.
- Product recommendations: product similarity search and training-data-driven suggestions.
- ContactInfo component: intent-aware display of support/contact details (separate from AI text).
- Real-time updates: broadcast new sources to frontend via events/channels.
- Queue & Scaling: Redis queues, Laravel Horizon for job monitoring.
- Security: Sanctum auth, rate limiting, input validation.

## Technical Stack
- Backend: PHP (Laravel 12)
- Frontend: React + Inertia.js, Tailwind CSS v4
- Database: PostgreSQL (pgvector extension)
- Vector embeddings: OpenAI text-embedding-3-small (1536 dims)
- Chat model: OpenAI chat model (configurable via OPENAI_CHAT_MODEL)
- Background processing: Redis queues, Laravel Horizon
- Dev tooling: Composer, npm, Vite
- Deployment: Docker (provided Dockerfile, docker-compose)

## Architecture Overview
- Ingestion: `ProcessSourceContent` job extracts, cleans, chunks content.
- Embeddings: `OpenAIService` + `EmbeddingCacheService` generate and cache embeddings.
- Storage: `sources` table stores content + embedding vectors; `sources_embedding_idx` uses ivfflat.
- Query flow: user query → embedding → vector search (`Source::findSimilar`) → build context → `RAGService` + `OpenAIService` → response.
- Learning loop: `LearningService` records interactions into `chatbot_learning_data` for future recommendations.
- Frontend: `Test.jsx` chat UI renders markdown, shows product suggestions and `ContactInfo` when flagged.

## Database Notes
- Enable pgvector: `CREATE EXTENSION IF NOT EXISTS vector;`
- Index: `CREATE INDEX sources_embedding_idx ON sources USING ivfflat (embedding vector_cosine_ops);`
- Key tables: `chatbots`, `sources`, `conversations`, `messages`, `chatbot_learning_data`, `products`

## Evaluation & Testing
- Functional tests for ingestion, embedding generation, and vector search.
- Integration tests for chat API (`/api/chat`) ensuring context continuity and RAG behavior.
- Performance checks: `EXPLAIN ANALYZE` on vector queries; measure response latency.
- User testing: qualitative review of real conversations and feedback collection.

## Deliverables
- Updated project proposal document (this file).
- README with setup and run instructions (`composer install`, `npm install`, `php artisan migrate`, enable `vector` extension).
- Example environment config entries: `OPENAI_API_KEY`, `OPENAI_EMBEDDING_MODEL=text-embedding-3-small`, DB and Redis settings.
- Optional export: Word/PDF version (created alongside this file).

## Timeline (suggested for a semester project)
- Week 1–2: Data model finalization, environment setup, pgvector enablement.
- Week 3–5: Source ingestion & embedding pipeline, background jobs.
- Week 6–8: Vector search + RAG response generation, system prompt tuning.
- Week 9–10: Adaptive learning + product recommendation integration.
- Week 11: Frontend chat UI polish, ContactInfo component, real-time updates.
- Week 12: Testing, evaluation, final report & demo preparation.

## Setup Quick Commands
```bash
composer install
npm install
php artisan migrate
# Enable pgvector in your DB:
psql -d your_db_name -c "CREATE EXTENSION IF NOT EXISTS vector;"
npm run dev
php artisan queue:work
```

## Notes & Next Steps
- I can also commit this file and create a PDF/Word export if you want the repo updated.
- Tell me if you want additional sections (risks, cost estimates, user studies) added.

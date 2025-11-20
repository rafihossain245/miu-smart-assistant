<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');

            // Input pattern and response
            $table->text('input_pattern')->comment('User input that triggered this response');
            $table->text('generated_response')->comment('AI-generated response that was used');
            $table->text('context')->nullable()->comment('Additional context used for generation');

            // Pattern classification
            $table->string('pattern_type')->default('greeting')->comment('Type: greeting, question, request, etc.');
            $table->string('intent')->nullable()->comment('Detected user intent');
            $table->json('extracted_entities')->nullable()->comment('Named entities extracted from input');

            // Vector embeddings for similarity search
            $table->vector('input_embedding', 1536)->comment('OpenAI embedding of input pattern');
            $table->vector('response_embedding', 1536)->comment('OpenAI embedding of response');

            // Performance metrics
            $table->decimal('similarity_score', 5, 4)->nullable()->comment('Similarity to existing patterns');
            $table->integer('usage_count')->default(1)->comment('How many times this pattern was matched');
            $table->decimal('avg_response_time', 8, 2)->nullable()->comment('Average response time in ms');

            // Quality metrics
            $table->decimal('quality_score', 3, 2)->nullable()->comment('Quality score 0-10 based on feedback');
            $table->integer('positive_feedback')->default(0)->comment('Positive feedback count');
            $table->integer('negative_feedback')->default(0)->comment('Negative feedback count');

            // Metadata
            $table->json('metadata')->nullable()->comment('Additional metadata (user agent, session info, etc.)');
            $table->timestamp('last_used_at')->nullable()->comment('When this pattern was last matched');
            $table->timestamps();

            // Indexes for performance
            $table->index(['chatbot_id', 'pattern_type']);
            $table->index(['chatbot_id', 'usage_count']);
            $table->index(['chatbot_id', 'quality_score']);
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_patterns');
    }
};
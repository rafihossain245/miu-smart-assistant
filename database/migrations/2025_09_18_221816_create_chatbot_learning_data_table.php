<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_learning_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->text('user_query');
            $table->text('bot_response');
            $table->json('context_used')->nullable(); // Sources that were used
            $table->json('user_feedback')->nullable(); // Thumbs up/down, ratings, etc.
            $table->text('user_correction')->nullable(); // If user provides better answer
            $table->float('similarity_score')->nullable(); // How well sources matched
            $table->integer('response_time_ms')->nullable(); // Response time
            $table->json('query_patterns')->nullable(); // Extracted patterns from query
            $table->json('behavior_data')->nullable(); // User interaction patterns
            $table->enum('interaction_type', ['question', 'feedback', 'correction', 'clarification'])->default('question');
            $table->boolean('was_helpful')->nullable(); // User indicated if response was helpful
            $table->timestamps();

            $table->index(['chatbot_id', 'created_at']);
            $table->index(['conversation_id']);
            $table->index(['interaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_learning_data');
    }
};
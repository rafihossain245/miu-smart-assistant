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
        Schema::create('chatbot_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');
            $table->text('question');
            $table->text('answer');
            $table->json('question_embedding')->nullable();
            $table->json('answer_embedding')->nullable();
            $table->integer('usage_count')->default(0);
            $table->decimal('accuracy_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['chatbot_id', 'question']);
            $table->index(['chatbot_id', 'usage_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_training_data');
    }
};

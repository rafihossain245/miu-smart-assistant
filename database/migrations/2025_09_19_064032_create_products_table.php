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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');

            // Product/Service Information
            $table->string('name')->comment('Product or service name');
            $table->enum('type', ['product', 'service'])->default('product');
            $table->text('description')->comment('Detailed description');
            $table->text('short_description')->nullable()->comment('Brief description for quick reference');

            // Pricing and Features
            $table->json('pricing')->nullable()->comment('Pricing tiers, plans, or custom pricing');
            $table->json('features')->nullable()->comment('Key features and benefits');
            $table->json('specifications')->nullable()->comment('Technical specs or service details');

            // URLs and Resources
            $table->string('primary_url')->nullable()->comment('Main product/service page URL');
            $table->string('demo_url')->nullable()->comment('Demo or trial URL');
            $table->string('documentation_url')->nullable()->comment('Documentation or help URL');
            $table->json('additional_urls')->nullable()->comment('Other relevant URLs');

            // Media and Assets
            $table->string('image_url')->nullable()->comment('Product image or icon');
            $table->json('gallery_urls')->nullable()->comment('Additional images or screenshots');
            $table->string('video_url')->nullable()->comment('Product demo or overview video');

            // Target Market and Use Cases
            $table->json('target_audience')->nullable()->comment('Who this is for (startups, enterprises, etc.)');
            $table->json('use_cases')->nullable()->comment('Common use cases and scenarios');
            $table->json('industries')->nullable()->comment('Target industries');

            // AI Training Data
            $table->text('ai_summary')->nullable()->comment('AI-friendly summary for chatbot responses');
            $table->json('common_questions')->nullable()->comment('Frequently asked questions about this product/service');
            $table->json('key_benefits')->nullable()->comment('Main selling points and advantages');
            $table->json('competitors')->nullable()->comment('How it compares to alternatives');

            // SEO and Marketing
            $table->json('keywords')->nullable()->comment('SEO keywords and search terms');
            $table->text('meta_description')->nullable()->comment('SEO meta description');
            $table->json('tags')->nullable()->comment('Categorization tags');

            // Vector embeddings for AI
            $table->vector('description_embedding', 1536)->nullable()->comment('OpenAI embedding of description');
            $table->vector('features_embedding', 1536)->nullable()->comment('OpenAI embedding of features');

            // Status and Analytics
            $table->boolean('is_active')->default(true)->comment('Is this product/service active?');
            $table->boolean('is_featured')->default(false)->comment('Feature in chatbot responses?');
            $table->integer('mention_count')->default(0)->comment('How often mentioned in conversations');
            $table->decimal('conversion_score', 5, 2)->nullable()->comment('Success rate when mentioned');

            // Metadata
            $table->json('metadata')->nullable()->comment('Additional custom fields');
            $table->integer('sort_order')->default(0)->comment('Display order');
            $table->timestamps();

            // Indexes
            $table->index(['chatbot_id', 'type']);
            $table->index(['chatbot_id', 'is_active']);
            $table->index(['chatbot_id', 'is_featured']);
            $table->index('mention_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
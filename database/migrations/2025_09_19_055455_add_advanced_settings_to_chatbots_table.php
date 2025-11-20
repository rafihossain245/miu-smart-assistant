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
        Schema::table('chatbots', function (Blueprint $table) {
            // Service/Product URLs for learning
            $table->json('service_urls')->nullable()->comment('URLs to learn about services/products');

            // Customer personas (target audience)
            $table->json('customer_personas')->nullable()->comment('Target customer types');

            // Brand voice settings
            $table->string('brand_voice', 100)->default('professional_friendly')->comment('Brand voice tone');

            // Customer query examples
            $table->json('customer_query_examples')->nullable()->comment('Example customer queries');

            // Integration settings
            $table->json('integrations')->nullable()->comment('Third-party integrations config');

            // Conversation intelligence settings
            $table->boolean('conversation_memory_enabled')->default(true)->comment('Enable conversation context memory');
            $table->integer('conversation_memory_limit')->default(10)->comment('Number of messages to remember');

            // Lead qualification settings
            $table->boolean('lead_qualification_enabled')->default(false)->comment('Enable smart lead qualification');
            $table->json('qualification_fields')->nullable()->comment('Fields to qualify leads');

            // Handoff settings
            $table->boolean('smart_handoff_enabled')->default(true)->comment('Enable smart handoff to humans');
            $table->json('handoff_triggers')->nullable()->comment('Conditions for handoff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chatbots', function (Blueprint $table) {
            $table->dropColumn([
                'service_urls',
                'customer_personas',
                'brand_voice',
                'customer_query_examples',
                'integrations',
                'conversation_memory_enabled',
                'conversation_memory_limit',
                'lead_qualification_enabled',
                'qualification_fields',
                'smart_handoff_enabled',
                'handoff_triggers'
            ]);
        });
    }
};

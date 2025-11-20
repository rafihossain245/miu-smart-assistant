<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add vector indexes for better similarity search performance

        // Create IVFFLAT index for sources embedding with optimized list count
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sources_embedding_ivfflat
                      ON sources USING ivfflat (embedding vector_cosine_ops)
                      WITH (lists = 100)');

        // Create composite index for sources with common filters
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sources_chatbot_status_embedding
                      ON sources (chatbot_id, status)
                      WHERE embedding IS NOT NULL AND status = \'completed\'');

        // Add index for products description embedding
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_desc_embedding_ivfflat
                      ON products USING ivfflat (description_embedding vector_cosine_ops)
                      WITH (lists = 50)');

        // Add index for products features embedding
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_feat_embedding_ivfflat
                      ON products USING ivfflat (features_embedding vector_cosine_ops)
                      WITH (lists = 50)');

        // Create composite index for products with common filters
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_chatbot_active_embedding
                      ON products (chatbot_id, is_active)
                      WHERE description_embedding IS NOT NULL AND is_active = true');

        // Note: Learning data doesn't have query_embedding column yet
        // This would need to be added in a future migration if needed

        // Add standard indexes for frequently queried columns
        Schema::table('sources', function (Blueprint $table) {
            $table->index(['chatbot_id', 'created_at'], 'idx_sources_chatbot_created');
            $table->index(['status', 'updated_at'], 'idx_sources_status_updated');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['chatbot_id', 'is_featured', 'is_active'], 'idx_products_chatbot_featured_active');
            $table->index(['created_at'], 'idx_products_created');
        });

        Schema::table('chatbot_learning_data', function (Blueprint $table) {
            $table->index(['chatbot_id', 'created_at'], 'idx_learning_chatbot_created');
            $table->index(['similarity_score'], 'idx_learning_similarity');
            $table->index(['was_helpful'], 'idx_learning_helpful');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop vector indexes
        DB::statement('DROP INDEX IF EXISTS idx_sources_embedding_ivfflat');
        DB::statement('DROP INDEX IF EXISTS idx_sources_chatbot_status_embedding');
        DB::statement('DROP INDEX IF EXISTS idx_products_desc_embedding_ivfflat');
        DB::statement('DROP INDEX IF EXISTS idx_products_feat_embedding_ivfflat');
        DB::statement('DROP INDEX IF EXISTS idx_products_chatbot_active_embedding');
        // Note: Learning data query_embedding index was not created

        // Drop standard indexes
        Schema::table('sources', function (Blueprint $table) {
            $table->dropIndex('idx_sources_chatbot_created');
            $table->dropIndex('idx_sources_status_updated');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_chatbot_featured_active');
            $table->dropIndex('idx_products_created');
        });

        Schema::table('chatbot_learning_data', function (Blueprint $table) {
            $table->dropIndex('idx_learning_chatbot_created');
            $table->dropIndex('idx_learning_similarity');
            $table->dropIndex('idx_learning_helpful');
        });
    }
};

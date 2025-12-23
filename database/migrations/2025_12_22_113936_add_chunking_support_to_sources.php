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
        Schema::table('sources', function (Blueprint $table) {
            // Add chunking support columns
            $table->integer('parent_source_id')->nullable()->after('chatbot_id');
            $table->integer('chunk_index')->nullable()->after('parent_source_id');
            $table->integer('total_chunks')->nullable()->after('chunk_index');
            $table->integer('chunk_overlap')->default(200)->after('total_chunks');
            
            // Add indexes for performance
            $table->index('parent_source_id', 'idx_sources_parent');
            $table->index(['chatbot_id', 'parent_source_id', 'chunk_index'], 'idx_sources_chunk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            //
        });
        Schema::table('sources', function (Blueprint $table) {
            $table->dropIndex('idx_sources_parent');
            $table->dropIndex('idx_sources_chunk');
            $table->dropColumn(['parent_source_id', 'chunk_index', 'total_chunks', 'chunk_overlap']);
        });
    }
};

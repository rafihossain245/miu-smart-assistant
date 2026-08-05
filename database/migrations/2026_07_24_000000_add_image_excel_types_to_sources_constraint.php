<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');

        DB::statement("
            ALTER TABLE sources
            ADD CONSTRAINT sources_type_check
            CHECK (type IN (
                'url',
                'pdf',
                'youtube',
                'text',
                'sitemap',
                'youtube_playlist',
                'technical_issue',
                'image',
                'excel',
                'url_chunk',
                'pdf_chunk',
                'youtube_chunk',
                'text_chunk',
                'image_chunk',
                'excel_chunk',
                'technical_issue_chunk'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');

        DB::statement("
            ALTER TABLE sources
            ADD CONSTRAINT sources_type_check
            CHECK (type IN (
                'url',
                'pdf',
                'youtube',
                'text',
                'sitemap',
                'youtube_playlist',
                'technical_issue',
                'url_chunk',
                'pdf_chunk',
                'youtube_chunk',
                'text_chunk'
            ))
        ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                'pdf_chunk',
                'url_chunk',
                'youtube_chunk',
                'text_chunk'
            ))
        ");
    }

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
                'technical_issue'
            ))
        ");
    }
};

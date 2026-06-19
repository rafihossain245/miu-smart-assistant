<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old constraint
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');

        // Add new constraint with chunk types included
        DB::statement("\n            ALTER TABLE sources \n            ADD CONSTRAINT sources_type_check \n            CHECK (type IN (\n                'url', \n                'pdf', \n                'youtube', \n                'text', \n                'sitemap', \n                'youtube_playlist', \n                'technical_issue',\n                'pdf_chunk',\n                'url_chunk',\n                'youtube_chunk',\n                'text_chunk'\n            ))\n        ");
    }

    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');

        // Restore original constraint
        DB::statement("\n            ALTER TABLE sources \n            ADD CONSTRAINT sources_type_check \n            CHECK (type IN (\n                'url', \n                'pdf', \n                'youtube', \n                'text', \n                'sitemap', \n                'youtube_playlist', \n                'technical_issue'\n            ))\n        ");
    }
};
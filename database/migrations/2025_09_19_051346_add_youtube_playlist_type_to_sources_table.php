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
        // Drop the existing check constraint
        DB::statement("ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check");

        // Add new check constraint that includes youtube_playlist
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('url', 'pdf', 'youtube', 'text', 'sitemap', 'youtube_playlist'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the constraint with youtube_playlist
        DB::statement("ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check");

        // Restore the original constraint without youtube_playlist
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('url', 'pdf', 'youtube', 'text', 'sitemap'))");
    }
};

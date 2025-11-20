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
        // Drop the existing check constraint and create a new one with sitemap included
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('url', 'pdf', 'youtube', 'text', 'sitemap'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original constraint without sitemap
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_type_check');
        DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type IN ('url', 'pdf', 'youtube', 'text'))");
    }
};
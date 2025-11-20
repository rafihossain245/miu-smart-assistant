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
        // Drop the existing check constraint
        \DB::statement("ALTER TABLE sources DROP CONSTRAINT sources_type_check");

        // Add new check constraint with technical_issue included
        \DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type::text = ANY (ARRAY['url'::character varying, 'pdf'::character varying, 'youtube'::character varying, 'text'::character varying, 'sitemap'::character varying, 'youtube_playlist'::character varying, 'technical_issue'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the constraint
        \DB::statement("ALTER TABLE sources DROP CONSTRAINT sources_type_check");

        // Recreate without technical_issue
        \DB::statement("ALTER TABLE sources ADD CONSTRAINT sources_type_check CHECK (type::text = ANY (ARRAY['url'::character varying, 'pdf'::character varying, 'youtube'::character varying, 'text'::character varying, 'sitemap'::character varying, 'youtube_playlist'::character varying]::text[]))");
    }
};

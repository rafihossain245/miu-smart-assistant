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
        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_status_check');

        DB::statement("
            ALTER TABLE sources
            ADD CONSTRAINT sources_status_check
            CHECK (status IN (
                'pending',
                'pending_review',
                'processing',
                'completed',
                'failed'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Anything still awaiting review has no place in the old constraint.
        DB::table('sources')->where('status', 'pending_review')->delete();

        DB::statement('ALTER TABLE sources DROP CONSTRAINT IF EXISTS sources_status_check');

        DB::statement("
            ALTER TABLE sources
            ADD CONSTRAINT sources_status_check
            CHECK (status IN (
                'pending',
                'processing',
                'completed',
                'failed'
            ))
        ");
    }
};

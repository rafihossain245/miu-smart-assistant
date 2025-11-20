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
        Schema::table('conversations', function (Blueprint $table) {
            // Remove old columns if they exist
            if (Schema::hasColumn('conversations', 'user_question')) {
                $table->dropColumn(['user_question', 'bot_answer', 'sources']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('user_question')->nullable();
            $table->text('bot_answer')->nullable();
            $table->json('sources')->nullable();
        });
    }
};

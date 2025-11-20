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
        Schema::create('broadcast_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->default('log'); // reverb, pusher, log
            $table->boolean('enabled')->default(false);

            // Pusher settings
            $table->string('pusher_app_id')->nullable();
            $table->string('pusher_key')->nullable();
            $table->string('pusher_secret')->nullable();
            $table->string('pusher_cluster')->default('mt1');

            // Reverb settings
            $table->string('reverb_app_id')->nullable();
            $table->string('reverb_key')->nullable();
            $table->string('reverb_secret')->nullable();
            $table->string('reverb_host')->default('localhost');
            $table->integer('reverb_port')->default(8080);
            $table->string('reverb_scheme')->default('http');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_settings');
    }
};

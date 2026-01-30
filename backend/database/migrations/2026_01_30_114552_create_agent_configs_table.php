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
        Schema::create('agent_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('agent_type', 50); // theme_director, music_composer, visual_director, image_generator, video_composer
            $table->string('name')->default('Default');
            $table->text('system_prompt');
            $table->string('model')->nullable(); // OpenRouter model ID (e.g., google/gemini-2.0-flash-exp)
            $table->json('parameters')->nullable(); // temperature, max_tokens, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'agent_type']);
            $table->unique(['user_id', 'agent_type', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_configs');
    }
};

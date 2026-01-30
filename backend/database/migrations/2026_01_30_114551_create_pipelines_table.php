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
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mode', 20)->default('auto'); // auto, manual
            $table->string('status', 50)->default('pending'); // pending, running, paused, completed, failed
            $table->string('current_step', 50)->nullable(); // theme_director, music_composer, etc.
            $table->integer('current_step_progress')->default(0); // 0-100
            $table->json('config')->nullable(); // pipeline configuration
            $table->json('steps_state')->nullable(); // state of each step
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};

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
        Schema::create('pipeline_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->onDelete('cascade');
            $table->string('agent_type', 50);
            $table->string('log_type', 20)->default('info'); // info, progress, result, error, thinking
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['pipeline_id', 'created_at']);
            $table->index(['pipeline_id', 'agent_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_logs');
    }
};

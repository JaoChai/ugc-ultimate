<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('cron_expression', 100);
            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // theme, duration, style
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};

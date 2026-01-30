<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('type', 50); // music, image, video_clip, final_video
            $table->string('filename');
            $table->text('url'); // R2 URL
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('metadata')->nullable(); // additional info
            $table->string('kie_task_id')->nullable(); // track kie.ai task
            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index('kie_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

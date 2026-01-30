<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('service', 50); // 'kie', 'r2'
            $table->string('name');
            $table->text('key_encrypted');
            $table->integer('credits_remaining')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};

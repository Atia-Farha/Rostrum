<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('round_id');
            $table->enum('speaker', ['user', 'ai']);
            $table->text('transcript');
            $table->string('audio_path')->nullable(); // AI turns only
            $table->string('ai_move_type')->nullable(); // Sparring Mode only
            $table->timestamps();

            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turns');
    }
};

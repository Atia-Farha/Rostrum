<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id')->index();
            $table->uuid('motion_id');
            $table->enum('user_side', ['government', 'opposition']);
            $table->uuid('persona_id');
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced', 'world_champion'])->default('intermediate');
            $table->enum('mode', ['tournament', 'sparring'])->default('tournament');
            $table->enum('language', ['en', 'bn']);
            $table->enum('status', ['setup', 'in_progress', 'adjudicated'])->default('setup');
            $table->timestamps();

            $table->foreign('motion_id')->references('id')->on('motions');
            $table->foreign('persona_id')->references('id')->on('personas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debates');
    }
};

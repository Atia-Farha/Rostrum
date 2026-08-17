<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_rewrites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('turn_id');
            $table->text('original_text');
            $table->text('rewritten_text');
            $table->json('explanation_bullets'); // e.g. ["added a concrete example", ...]
            $table->timestamps();

            $table->foreign('turn_id')->references('id')->on('turns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_rewrites');
    }
};

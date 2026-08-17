<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('debate_id');
            // null in Sparring Mode (uses a single implicit round per debate)
            $table->enum('phase', ['opening', 'rebuttal', 'closing'])->nullable();
            $table->integer('phase_order');
            $table->timestamps();

            $table->foreign('debate_id')->references('id')->on('debates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes on frequently-queried foreign-key columns so round views,
 * history, and adjudication lookups stay fast as debate data grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->index('debate_id');
        });
        Schema::table('turns', function (Blueprint $table) {
            $table->index('round_id');
        });
        Schema::table('turn_rewrites', function (Blueprint $table) {
            $table->index('turn_id');
        });
        Schema::table('adjudications', function (Blueprint $table) {
            $table->index('debate_id');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropIndex(['debate_id']);
        });
        Schema::table('turns', function (Blueprint $table) {
            $table->dropIndex(['round_id']);
        });
        Schema::table('turn_rewrites', function (Blueprint $table) {
            $table->dropIndex(['turn_id']);
        });
        Schema::table('adjudications', function (Blueprint $table) {
            $table->dropIndex(['debate_id']);
        });
    }
};

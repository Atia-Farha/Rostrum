<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debates', function (Blueprint $table) {
            $table->string('ai_provider')->nullable()->after('status');
            $table->text('ai_error')->nullable()->after('ai_provider');
        });

        Schema::table('turns', function (Blueprint $table) {
            $table->string('audio_provider')->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->dropColumn('audio_provider');
        });

        Schema::table('debates', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'ai_error']);
        });
    }
};

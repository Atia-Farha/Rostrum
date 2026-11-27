<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropColumn(['elevenlabs_voice_id', 'elevenlabs_voice_id_bn']);
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->string('elevenlabs_voice_id')->nullable();
            $table->string('elevenlabs_voice_id_bn')->nullable();
        });
    }
};

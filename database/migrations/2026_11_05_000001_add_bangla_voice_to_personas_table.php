<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each persona a second voice — a Bangla one.
 *
 * A persona used to carry exactly one ElevenLabs voice ID, and that voice
 * had to serve both languages the app debates in. The English premade
 * voices read Bangla passably but not well: the phonetics are built for
 * English, and a Bangla debate heard an English mouth.
 *
 * The Bangla voices chosen for the personas are native recordings — people
 * who actually speak Bangla — so the Bangla debate now sounds like a
 * debate in Bangla rather than a translation of one.
 *
 * The column is added as NULLABLE and the existing rows are backfilled by
 * persona name. A persona with no Bangla voice keeps speaking with its
 * English one, so the app degrades to the old behaviour rather than going
 * silent if a persona is added without a Bangla voice.
 *
 * English is untouched: `elevenlabs_voice_id` remains the English voice,
 * and `elevenlabs_voice_id_bn` is the Bangla one.
 */
return new class extends Migration
{
    /**
     * The Bangla voice for each seeded persona, keyed by persona name.
     *
     * These are native Bangla recordings from the ElevenLabs voice
     * library, chosen to match the character each persona argues in.
     */
    private const BANGLA_VOICES = [
        'Calm Logician'             => 'd532cgnzlrx7AWL3CD9i', // Fahad Mir - Casual
        'Aggressive Cross-Examiner' => 'vvOYlSJHf8lTWIG4tDNP', // Rayhan - Engaging & Excitement
        "Devil's Advocate"          => 'VizHiwkPO7hXc967v4tE', // Rakib - Deep, Authoritative
    ];

    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            // Nullable: a persona without a Bangla voice falls back to its
            // English one rather than failing to synthesise.
            $table->string('elevenlabs_voice_id_bn')->nullable()->after('elevenlabs_voice_id');
        });

        foreach (self::BANGLA_VOICES as $name => $voiceId) {
            DB::table('personas')
                ->where('name', $name)
                ->update(['elevenlabs_voice_id_bn' => $voiceId]);
        }
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn('elevenlabs_voice_id_bn');
        });
    }
};

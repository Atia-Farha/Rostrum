<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    */
    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs
    |--------------------------------------------------------------------------
    */
    'elevenlabs_model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),

    /*
    |--------------------------------------------------------------------------
    | TTS Max Characters
    | The AI transcript is trimmed to this many characters (at a sentence
    | boundary) before it is sent to ElevenLabs. The full text is still shown
    | in the transcript. When the account has few credits left, the request
    | is automatically retried with an even shorter text that fits the
    | remaining quota — so a debate never runs completely silent.
    |--------------------------------------------------------------------------
    */
    'tts_max_chars' => (int) env('ELEVENLABS_TTS_MAX_CHARS', 1500),

    /*
    |--------------------------------------------------------------------------
    | Phase Time Limits (seconds)
    |--------------------------------------------------------------------------
    */
    'phase_duration' => [
        'opening'  => 180, // 3 min
        'rebuttal' => 120, // 2 min
        'closing'  => 120, // 2 min
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Word Count Ranges per Phase
    | Keeps AI response length proportionate to the phase time budget (§12.1)
    |--------------------------------------------------------------------------
    */
    'ai_word_count' => [
        'opening'  => '150–220',
        'rebuttal' => '100–160',
        'closing'  => '100–160',
        'sparring' => '100–160',
    ],

    /*
    |--------------------------------------------------------------------------
    | Difficulty Prompt Fragments
    | Appended to the persona's system_prompt at call time (§6.2a, §14)
    |--------------------------------------------------------------------------
    */
    'difficulty_prompts' => [
        'beginner' => 'Argue simply and clearly, as a friendly practice partner would. Let minor weaknesses in your opponent\'s case go unchallenged so they can build confidence. Use straightforward vocabulary and avoid complex rhetorical moves.',

        'intermediate' => 'Argue at a solid club-level standard. Engage directly with your opponent\'s main points, use one or two concrete examples, and maintain a clear logical structure throughout your speech.',

        'advanced' => 'Argue at a competitive tournament standard. Use precise evidence and real-world examples. Identify and target the weakest premise in your opponent\'s case. Employ sophisticated rhetorical structures (e.g. link-turns, impact calculus). Leave no major claim by your opponent unaddressed.',

        'world_champion' => 'Argue at a tournament-champion skill level. Use precise evidence and examples. Actively identify the single weakest link in your opponent\'s most recent point and target it directly. Employ advanced debate techniques: pre-empting likely responses, framing the round\'s core clash, and making explicit impact comparisons. Your arguments should be airtight.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Motion Categories
    |--------------------------------------------------------------------------
    */
    'motion_categories' => [
        'politics',
        'technology',
        'society',
        'environment',
        'education',
        'economy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Seed Demo Session
    | The seeded adjudicated demo debate (TournamentModeSeeder) is shown in
    | every visitor's history so the product demo is never empty.
    |--------------------------------------------------------------------------
    */
    'seed_session_id' => 'seed-tournament-demo-001',

    /*
    |--------------------------------------------------------------------------
    | Sparring Move Types
    |--------------------------------------------------------------------------
    */
    'sparring_move_types' => [
        'follow_up',
        'contradiction',
        'challenge',
        'rebuttal',
    ],

];

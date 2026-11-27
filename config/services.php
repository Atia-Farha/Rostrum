<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'tts' => [
            'model' => env('GEMINI_TTS_MODEL'),
            'timeout' => (int) env('GEMINI_TTS_TIMEOUT', 45),
            'voices' => [
                'en' => env('GEMINI_TTS_VOICE_EN', 'Kore'),
                'bn' => env('GEMINI_TTS_VOICE_BN', 'Kore'),
            ],
        ],
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'reasoning' => [
            'models' => [
                env('GROQ_REASONING_MODEL'),
                env('GROQ_REASONING_FALLBACK_MODEL_1'),
                env('GROQ_REASONING_FALLBACK_MODEL_2'),
            ],
            'timeout' => (int) env('GROQ_REASONING_TIMEOUT', 60),
        ],
        'stt' => [
            'models' => [
                env('GROQ_STT_MODEL'),
                env('GROQ_STT_FALLBACK_MODEL'),
            ],
            'timeout' => (int) env('GROQ_STT_TIMEOUT', 60),
        ],
    ],

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
        'stt' => [
            'model' => env('ELEVENLABS_STT_MODEL'),
            'timeout' => (int) env('ELEVENLABS_STT_TIMEOUT', 60),
        ],
    ],

    'edge_tts' => [
        'binary' => env('EDGE_TTS_BINARY', 'edge-tts'),
        'timeout' => (int) env('EDGE_TTS_TIMEOUT', 45),
        'voices' => [
            'en' => env('EDGE_TTS_VOICE_EN', 'en-US-AvaMultilingualNeural'),
            'bn' => env('EDGE_TTS_VOICE_BN', 'bn-BD-NabanitaNeural'),
        ],
    ],

];

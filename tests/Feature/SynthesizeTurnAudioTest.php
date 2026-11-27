<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SynthesizeTurnAudio;
use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use App\Services\EdgeTtsService;
use App\Services\Exceptions\TtsProviderException;
use App\Services\GeminiTtsService;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class SynthesizeTurnAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_edge_tts_only_after_a_retryable_gemini_failure(): void
    {
        $this->seed(PersonaSeeder::class);
        $debate = Debate::create([
            'session_id' => 'tts-test',
            'user_id' => User::factory()->create()->id,
            'motion_id' => Motion::create(['text_en' => 'This House tests speech.', 'source' => 'manual'])->id,
            'persona_id' => Persona::firstOrFail()->id,
            'user_side' => 'government',
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'bn',
            'status' => 'in_progress',
        ]);
        $turn = Turn::create([
            'round_id' => Round::create(['debate_id' => $debate->id, 'phase' => 'opening', 'phase_order' => 1])->id,
            'speaker' => 'ai',
            'transcript' => 'মূল বক্তব্য।',
        ]);

        $gemini = Mockery::mock(GeminiTtsService::class);
        $gemini->shouldReceive('synthesize')->once()
            ->with('মূল বক্তব্য।', 'bn')
            ->andThrow(new TtsProviderException('Service unavailable.', true));
        $edge = Mockery::mock(EdgeTtsService::class);
        $edge->shouldReceive('synthesize')->once()
            ->with('মূল বক্তব্য।', 'bn')
            ->andReturn('edge_tts_generated.mp3');

        (new SynthesizeTurnAudio($debate, $turn))->handle($gemini, $edge);

        $this->assertSame('edge_tts_generated.mp3', $turn->refresh()->audio_path);
        $this->assertSame('edge_tts', $turn->audio_provider);
    }

    public function test_it_persists_a_recoverable_error_when_no_tts_provider_can_generate_audio(): void
    {
        $this->seed(PersonaSeeder::class);
        $debate = Debate::create([
            'session_id' => 'tts-error-test',
            'user_id' => User::factory()->create()->id,
            'motion_id' => Motion::create(['text_en' => 'This House tests speech.', 'source' => 'manual'])->id,
            'persona_id' => Persona::firstOrFail()->id,
            'user_side' => 'government',
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
            'status' => 'in_progress',
        ]);
        $turn = Turn::create([
            'round_id' => Round::create(['debate_id' => $debate->id, 'phase' => 'opening', 'phase_order' => 1])->id,
            'speaker' => 'ai',
            'transcript' => 'A text-only response.',
        ]);

        $gemini = Mockery::mock(GeminiTtsService::class);
        $gemini->shouldReceive('synthesize')->once()
            ->andThrow(new TtsProviderException('Service unavailable.', true));
        $edge = Mockery::mock(EdgeTtsService::class);
        $edge->shouldReceive('synthesize')->once()
            ->andThrow(new \RuntimeException('Fallback unavailable.'));

        (new SynthesizeTurnAudio($debate, $turn))->handle($gemini, $edge);

        $this->assertNull($turn->refresh()->audio_path);
        $this->assertSame('Audio is unavailable right now. You can continue reading the response.', $turn->audio_error);
    }
}

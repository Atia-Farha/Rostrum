<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EdgeTtsService;
use App\Services\Exceptions\TtsProviderException;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class EdgeTtsServiceTest extends TestCase
{
    public function test_it_uses_the_configured_bangla_voice_and_reports_a_provider_failure(): void
    {
        config([
            'services.edge_tts.binary' => 'configured-edge-tts',
            'services.edge_tts.timeout' => 30,
            'services.edge_tts.voices.en' => 'configured-english-voice',
            'services.edge_tts.voices.bn' => 'configured-bangla-voice',
        ]);
        Process::fake(['*' => Process::result(exitCode: 1)]);

        try {
            app(EdgeTtsService::class)->synthesize('বাংলা বক্তব্য', 'bn');
            $this->fail('Expected Edge TTS to fail.');
        } catch (TtsProviderException $exception) {
            $this->assertFalse($exception->shouldFallback);
        }

        Process::assertRan(fn ($process): bool => $process->command[0] === 'configured-edge-tts'
            && in_array('configured-bangla-voice', $process->command, true)
            && in_array('বাংলা বক্তব্য', $process->command, true));
    }
}

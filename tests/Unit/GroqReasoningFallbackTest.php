<?php

namespace Tests\Unit;

use App\Services\GroqReasoningService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqReasoningFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.groq.key', 'test-groq-key');
        config()->set('services.groq.reasoning.models', [
            'primary-model',
            'first-fallback-model',
            'second-fallback-model',
        ]);
    }

    public function test_it_preserves_messages_while_falling_back_in_priority_order(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => ['message' => 'model is temporarily unavailable']], 503)
                ->push(['error' => ['message' => 'capacity unavailable']], 503)
                ->push(['choices' => [['message' => ['content' => 'Recovered response.']]]]),
        ]);

        $service = resolve(GroqReasoningService::class);
        $response = $service->generateWithSystem('System context.', 'User context.');

        $this->assertSame('Recovered response.', $response);
        $this->assertSame('second-fallback-model', $service->lastModel());
        Http::assertSentCount(3);

        $models = array_map(
            fn (array $recording): string => $recording[0]->data()['model'],
            Http::recorded()->all(),
        );
        $this->assertSame(['primary-model', 'first-fallback-model', 'second-fallback-model'], $models);

        foreach (Http::recorded()->all() as [$request]) {
            $this->assertSame([
                ['role' => 'system', 'content' => 'System context.'],
                ['role' => 'user', 'content' => 'User context.'],
            ], $request->data()['messages']);
        }
    }

    public function test_it_does_not_fallback_for_an_organization_wide_rate_limit(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Organization rate limit exceeded for requests per day'],
            ], 429),
        ]);

        $this->expectExceptionMessage('rate limit');

        try {
            resolve(GroqReasoningService::class)->generateWithSystem('System context.', 'User context.');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_it_falls_back_after_a_malformed_success_response(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => '']]]])
                ->push(['choices' => [['message' => ['content' => 'Valid response.']]]]),
        ]);

        $response = resolve(GroqReasoningService::class)->generateWithSystem('System context.', 'User context.');

        $this->assertSame('Valid response.', $response);
        Http::assertSentCount(2);
    }

    public function test_it_can_consume_a_streamed_response_without_losing_its_deltas(): void
    {
        Http::fake([
            '*' => Http::response(implode("\n\n", [
                'data: {"choices":[{"delta":{"content":"Streamed "}}]}',
                'data: {"choices":[{"delta":{"content":"response."}}]}',
                'data: [DONE]',
            ])),
        ]);

        $deltas = [];
        $response = resolve(GroqReasoningService::class)->generateWithSystem(
            'System context.',
            'User context.',
            onDelta: function (string $delta) use (&$deltas): void {
                $deltas[] = $delta;
            },
        );

        $this->assertSame('Streamed response.', $response);
        $this->assertSame(['Streamed ', 'response.'], $deltas);
        Http::assertSent(fn ($request): bool => $request->data()['stream'] === true);
    }

    public function test_it_returns_a_recoverable_error_after_every_model_fails(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'service unavailable']], 503),
        ]);

        $this->expectExceptionMessage('temporarily unavailable');

        try {
            resolve(GroqReasoningService::class)->generateWithSystem('System context.', 'User context.');
        } finally {
            Http::assertSentCount(3);
        }
    }
}

<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * What the reader is told when Gemini refuses a request.
 *
 * A 429 is not one thing. A per-minute rate limit clears in seconds,
 * and a daily free-tier quota does not — Google says so in the body,
 * with the time until it resets. Telling someone to "wait a few
 * seconds" for a quota that resets tomorrow makes a working app look
 * broken, so the two are told apart.
 */
class GeminiRateLimitTest extends TestCase
{
    private function messageFor(string $body): string
    {
        $method = new ReflectionMethod(GeminiService::class, 'rateLimitMessage');
        $method->setAccessible(true);

        return $method->invoke(resolve(GeminiService::class), $body);
    }

    public function test_a_transient_rate_limit_says_to_wait_a_few_seconds(): void
    {
        $message = $this->messageFor('{"error":{"code":429,"message":"Resource has been exhausted"}}');

        $this->assertStringContainsString('high traffic', $message);
        $this->assertStringContainsString('a few seconds', $message);
    }

    public function test_a_daily_quota_says_the_limit_has_been_reached(): void
    {
        // This is the body Google returned verbatim.
        $body = 'Quota exceeded for metric: generativelanguage.googleapis.com/generate_content_free_tier_requests, '
            .'limit: 20, model: gemini-3.6-flash, description: Generate content requests per day limit exceeded '
            .'on Free Tier. Please retry in 17h18m8.03626646s.';

        $message = $this->messageFor($body);

        $this->assertStringContainsString('daily request limit', $message);
        $this->assertStringNotContainsString('a few seconds', $message);
    }

    public function test_the_reset_time_is_passed_on(): void
    {
        // The seconds carry a fractional part, so a pattern that expects
        // whole seconds loses the whole match and the reader is told
        // nothing about when it resets. The fraction is then dropped,
        // because it reads as noise in a message meant for a debater.
        $body = 'Quota exceeded: Generate content requests per day limit exceeded on Free Tier. '
            .'Please retry in 17h18m8.03626646s.';

        $message = $this->messageFor($body);

        $this->assertStringContainsString('17h18m8', $message);
        $this->assertStringContainsString('resets in about', $message);
        $this->assertStringNotContainsString('17h18m8.03626646s', $message);
    }
}

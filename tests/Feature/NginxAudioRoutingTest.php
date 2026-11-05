<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The nginx config decides whether a voice recording reaches Laravel at all.
 *
 * The recordings are streamed by DebateController::audio(), which checks
 * that the signed-in account owns the debate. The files live on the PRIVATE
 * disk — they are not under the web root — so nginx has to pass the request
 * to PHP.
 *
 * The static-asset block lists `webm` among the extensions it caches. nginx
 * matches regex locations in the order they appear, so if that block claims
 * /debates/{id}/audio/{file}.webm first, nginx looks for the file under the
 * web root, does not find it, and answers 404 without PHP ever running. The
 * turn still shows a Listen button, but pressing it plays nothing.
 *
 * The Laravel feature tests cannot catch this — they bypass nginx entirely —
 * so the routing rules are asserted here against the shipped config.
 */
class NginxAudioRoutingTest extends TestCase
{
    private function nginxConfig(): string
    {
        $path = base_path('docker/nginx.conf');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The audio route must be matched by a location block that appears
     * BEFORE the static-asset block, so the recording reaches PHP.
     */
    #[Test]
    public function the_audio_route_is_handled_before_the_static_asset_block(): void
    {
        $config = $this->nginxConfig();

        $audioAt = strpos($config, 'audio/');
        $staticAt = strpos($config, 'Cache static build assets');

        $this->assertNotFalse($audioAt, 'The nginx config has no audio location block.');
        $this->assertNotFalse($staticAt, 'The nginx config has no static-asset block.');
        $this->assertLessThan(
            $staticAt,
            $audioAt,
            'The audio location block must come before the static-asset block, '
                .'otherwise nginx claims the recording and answers 404 without running PHP.'
        );
    }

    /**
     * The audio location must fall through to the Laravel front controller
     * rather than being served straight off disk.
     */
    #[Test]
    public function the_audio_location_forwards_to_laravel(): void
    {
        $config = $this->nginxConfig();

        // The audio location block, taken verbatim from the config.
        $start = strpos($config, 'location ~* ^/debates/');

        $this->assertNotFalse($start, 'The nginx config has no audio location block.');

        $block = substr($config, $start, strpos($config, '}', $start) - $start + 1);

        $this->assertStringContainsString(
            'try_files $uri @laravel;',
            $block,
            'The audio location must forward the request to Laravel via the @laravel named location.'
        );
    }

    /**
     * The @laravel named location must exist and rewrite to index.php,
     * otherwise the forward above lands nowhere.
     */
    #[Test]
    public function the_laravel_named_location_exists(): void
    {
        $config = $this->nginxConfig();

        $this->assertMatchesRegularExpression(
            '/location\s+@laravel\s*\{[^}]*rewrite\s+\^\s+\/index\.php\s+last/s',
            $config,
            'The @laravel named location must rewrite to index.php.'
        );

        $this->assertMatchesRegularExpression(
            '/location\s+\/\s*\{[^}]*try_files[^}]*@laravel/s',
            $config,
            'The root location must fall through to @laravel.'
        );
    }

    /**
     * A recording is only playable if the file is on the private disk. If the
     * write fails the turn must not advertise a URL that points at nothing,
     * because the disk is configured with 'throw' => false and a failed put()
     * returns false without raising.
     */
    #[Test]
    public function a_failed_recording_write_is_not_reported_as_success(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/DebateController.php'));

        $this->assertStringContainsString(
            '$written === false',
            $source,
            'The controller must check the result of the recording write — the disk '
                .'returns false on failure instead of throwing.'
        );

        $this->assertStringContainsString(
            "Log::error('Could not store the user recording'",
            $source,
            'A failed recording write must be logged, not swallowed.'
        );
    }
}

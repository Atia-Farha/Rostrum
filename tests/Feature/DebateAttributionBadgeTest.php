<?php

namespace Tests\Feature;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the attribution belongs.
 *
 * Every speech used to carry the machinery that produced it — which
 * transcriber heard the human, which model reasoned, which voice read
 * the reply. Those badges said nothing about the argument and pushed
 * the speakers' names out of line with the speech itself.
 *
 * The attribution still belongs in the product, but it belongs in the
 * podium, once, where the reader can look up from the transcript and
 * see what they are talking to — not beside every line of the
 * conversation.
 *
 * These tests hold both halves of that: the feed stays quiet, and the
 * podium tells the reader the stack once, without repeating itself.
 */
class DebateAttributionBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    private function makeDebateWithTurns(User $user, string $language = 'en'): Debate
    {
        $motion = Motion::create([
            'text_en' => 'This House would ban social media for users under the age of 16.',
            'text_bn' => 'এই সভা মনে করে যে ১৬ বছরের কম বয়সীদের জন্য সোশ্যাল মিডিয়া নিষিদ্ধ করা উচিত।',
            'category' => 'Society',
            'source' => 'manual',
        ]);

        $debate = Debate::create([
            'session_id' => 'session-'.uniqid(),
            'user_id' => $user->id,
            'motion_id' => $motion->id,
            'persona_id' => Persona::first()->id,
            'user_side' => 'government',
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => $language,
            'status' => 'in_progress',
        ]);

        $round = Round::create([
            'debate_id' => $debate->id,
            'phase' => 'opening',
            'phase_order' => 1,
        ]);

        Turn::create([
            'round_id' => $round->id,
            'speaker' => 'user',
            'transcript' => 'The proposer has not addressed who loses when a minor loses their voice online.',
        ]);

        Turn::create([
            'round_id' => $round->id,
            'speaker' => 'ai',
            'transcript' => 'The opposition concedes the premise but disputes the remedy.',
            'ai_move_type' => 'rebuttal',
        ]);

        return $debate;
    }

    /**
     * The rendered page, split into the two panels the reader actually
     * sees: the conversation on the left, the podium on the right.
     */
    private function panels(User $user): array
    {
        $debate = $this->makeDebateWithTurns($user);

        $html = $this->actingAs($user)
            ->get(route('debates.show', $debate->id))
            ->assertOk()
            ->getContent();

        // The transcript feed is the conversation. Everything the
        // reader reads as an argument lives inside it.
        $feedStart = strpos($html, 'id="transcript-feed"');
        $this->assertNotFalse($feedStart, 'The arena should have a transcript feed.');

        // The podium is the other pane.
        $podiumStart = strpos($html, 'id="podium-pane"');
        $this->assertNotFalse($podiumStart, 'The arena should have a podium pane.');

        $this->assertLessThan(
            $podiumStart,
            $feedStart,
            'The transcript feed should come before the podium.',
        );

        // The feed ends where the podium pane begins.
        $feed = substr($html, $feedStart, $podiumStart - $feedStart);
        $podium = substr($html, $podiumStart);

        return [$feed, $podium];
    }

    /**
     * The feed must not name the transcriber on a human speech. The
     * reader knows who spoke; the vendor that heard them is noise.
     */
    public function test_the_conversation_does_not_advertise_the_transcriber(): void
    {
        [$feed] = $this->panels(User::factory()->create());

        $this->assertStringNotContainsString('Transcribed by', $feed);
        $this->assertStringNotContainsString('ElevenLabs Scribe', $feed);
    }

    /**
     * The feed must not name the reasoning model beside an AI speech.
     */
    public function test_the_conversation_does_not_advertise_the_reasoning_model(): void
    {
        [$feed] = $this->panels(User::factory()->create());

        $this->assertStringNotContainsString('Logic:', $feed);
        $this->assertStringNotContainsString('Gemini', $feed);
    }

    /**
     * The feed must not name the synthetic voice beside an AI speech.
     */
    public function test_the_conversation_does_not_advertise_the_voice(): void
    {
        [$feed] = $this->panels(User::factory()->create());

        $this->assertStringNotContainsString('Voice:', $feed);
        $this->assertStringNotContainsString('ElevenLabs', $feed);
    }

    /**
     * A speech that arrives while the round is live is rendered by
     * JavaScript, not by Blade. The live path must be as quiet as the
     * server-rendered one, or the badges come straight back the moment
     * the reader speaks.
     */
    public function test_the_live_speech_path_does_not_advertise_the_stack(): void
    {
        [$feed] = $this->panels(User::factory()->create());

        // The badge markup for the live path, and the provider branch
        // that selected it.
        $this->assertStringNotContainsString('sttBadgeHtml', $feed);
        $this->assertStringNotContainsString('stt_provider === ', $feed);
        $this->assertStringNotContainsString('Transcribed by Gemini Multimodal', $feed);
    }

    /**
     * The speaker names themselves must survive. Stripping the badges
     * is not an excuse for stripping the conversation.
     */
    public function test_the_speakers_are_still_named(): void
    {
        [$feed] = $this->panels(User::factory()->create());

        $this->assertStringContainsString('You (Government)', $feed);
        $this->assertStringContainsString(Persona::first()->name, $feed);
    }

    /**
     * The podium is where the reader looks up to find out what they
     * are talking to. All three parts of the stack are named there.
     */
    public function test_the_podium_names_the_logic_the_transcriber_and_the_voice(): void
    {
        [$feed, $podium] = $this->panels(User::factory()->create());

        $this->assertStringContainsString('Logic:', $podium);
        $this->assertStringContainsString('Gemini 3.6 Flash', $podium);

        $this->assertStringContainsString('Transcribe:', $podium);
        $this->assertStringContainsString('ElevenLabs Scribe', $podium);

        $this->assertStringContainsString('Voice:', $podium);
        $this->assertStringContainsString('ElevenLabs ('.Persona::first()->voiceName('en').')', $podium);
    }

    /**
     * One voice, named once.
     *
     * The podium used to carry the voice in two places at once — a row
     * in Match Info and a line in the AI Stack panel — so a reader
     * looking for the voice found it twice and could not tell whether
     * the two were the same answer or two different ones.
     */
    public function test_the_voice_is_not_repeated_in_the_podium(): void
    {
        [, $podium] = $this->panels(User::factory()->create());

        $this->assertSame(
            1,
            substr_count($podium, 'ElevenLabs ('.Persona::first()->voiceName('en').')'),
            'The podium should name the voice once.',
        );

        // And it should not be in Match Info as well.
        $this->assertSame(
            0,
            substr_count($podium, '<span>Voice</span>'),
            'Match Info should not repeat the voice already in the AI Stack.',
        );
    }

    /**
     * The stack is described once, not once per part of it.
     */
    public function test_the_stack_is_not_described_twice_in_the_podium(): void
    {
        [, $podium] = $this->panels(User::factory()->create());

        $this->assertSame(
            1,
            substr_count($podium, 'AI Stack'),
            'The podium should have one AI Stack panel.',
        );

        $this->assertSame(
            1,
            substr_count($podium, 'ElevenLabs Scribe'),
            'The transcriber should be named once.',
        );
    }

    /**
     * A Bangla debate is spoken by the Bangla voice.
     *
     * The podium names the voice that will actually be heard, so a reader
     * in a Bangla debate should be told about the Bangla voice — and not
     * about the English one, which is not speaking.
     */
    public function test_a_bangla_debate_names_the_bangla_voice(): void
    {
        $user = User::factory()->create();
        $debate = $this->makeDebateWithTurns($user, 'bn');
        $persona = $debate->persona;

        $html = $this->actingAs($user)
            ->get(route('debates.show', $debate->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'ElevenLabs ('.$persona->voiceName('bn').')',
            $html,
            'A Bangla debate should name the Bangla voice.',
        );

        $this->assertStringNotContainsString(
            'ElevenLabs ('.$persona->voiceName('en').')',
            $html,
            'A Bangla debate should not name the English voice.',
        );
    }
}

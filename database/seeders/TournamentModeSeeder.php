<?php

namespace Database\Seeders;

use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\TurnRewrite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * TournamentModeSeeder
 *
 * Seeds a fully resolved Tournament Mode debate:
 *   - 2 curated Motions (EN + BN)
 *   - 1 Debate (Tournament mode, status = adjudicated)
 *   - 3 Rounds  (Opening → Rebuttal → Closing)
 *   - 2 Turns per Round (user first, then AI) → 6 turns total
 *   - 1 TurnRewrite on a user turn (Opening round)
 *   - 1 Adjudication with full scoring, fallacies, feedback & verdict
 */
class TournamentModeSeeder extends Seeder
{
    public function run(): void
    {
        // ──────────────────────────────────────────────────────────
        // 1. Motions
        // ──────────────────────────────────────────────────────────
        $motion = Motion::firstOrCreate(
            ['text_en' => 'This House Would ban social media for users under the age of 16.'],
            [
                'text_en'  => 'This House Would ban social media for users under the age of 16.',
                'text_bn'  => 'এই হাউস ১৬ বছরের কম বয়সীদের জন্য সোশ্যাল মিডিয়া নিষিদ্ধ করবে।',
                'category' => 'Technology & Society',
                'source'   => 'manual',
            ]
        );

        Motion::firstOrCreate(
            ['text_en' => 'This House Believes that universal basic income would do more harm than good.'],
            [
                'text_en'  => 'This House Believes that universal basic income would do more harm than good.',
                'text_bn'  => 'এই হাউস বিশ্বাস করে যে সর্বজনীন বেসিক আয় ভালোর চেয়ে বেশি ক্ষতি করবে।',
                'category' => 'Economics',
                'source'   => 'manual',
            ]
        );

        // ──────────────────────────────────────────────────────────
        // 2. Resolve the "Calm Logician" persona (seeded by PersonaSeeder)
        // ──────────────────────────────────────────────────────────
        $persona = Persona::where('name', 'Calm Logician')->first();

        if (! $persona) {
            $this->command->warn('Persona "Calm Logician" not found — run PersonaSeeder first. Skipping tournament seed.');
            return;
        }

        // ──────────────────────────────────────────────────────────
        // 3. Debate
        // ──────────────────────────────────────────────────────────
        /** @var Debate $debate */
        $debate = Debate::create([
            'session_id' => config('debate.seed_session_id'),
            'motion_id'  => $motion->id,
            'user_side'  => 'government',
            'persona_id' => $persona->id,
            'difficulty' => 'intermediate',
            'mode'       => 'tournament',
            'language'   => 'en',
            'status'     => 'adjudicated',
        ]);

        // ──────────────────────────────────────────────────────────
        // 4. Rounds + Turns
        //    Tournament order (Government speaks first in Opening & Rebuttal,
        //    Opposition speaks first in Closing per standard BP rules)
        // ──────────────────────────────────────────────────────────

        // ── 4a. Opening ──────────────────────────────────────────
        $opening = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'opening',
            'phase_order' => 1,
        ]);

        $userOpeningTurn = Turn::create([
            'round_id'   => $opening->id,
            'speaker'    => 'user',
            'transcript' => 'The Government firmly supports this motion. Unrestricted access to social media is harming the mental health of adolescents at scale. Studies from the UK Mental Health Foundation show a 52% rise in anxiety among 13–15 year-olds directly correlated with heavy social media use. We propose a verified age-gate at sign-up — technically feasible and already mandated in countries like France. The state has a duty of care to protect minors from documented harm. We urge this House to support.',
            'audio_path' => null,
        ]);

        Turn::create([
            'round_id'   => $opening->id,
            'speaker'    => 'ai',
            'transcript' => 'The Opposition respectfully challenges the premise. The Government has conflated correlation with causation. A 52% rise in anxiety is tragic — but attributing it solely to social media ignores the confounding effects of academic pressure, economic insecurity, and the pandemic. Furthermore, a blanket ban is disproportionate. For students in rural areas with limited in-person peer networks, and for young artists and creators who rely on these platforms to share their work and build skills, social media provides genuine educational and social value. We would ask the Government: what evidence shows a blanket ban reduces harm versus simply relocating it to darker, unmoderated corners of the internet?',
            'audio_path' => null,
        ]);

        // Turn rewrite on the user's Opening turn
        TurnRewrite::create([
            'turn_id'          => $userOpeningTurn->id,
            'original_text'    => $userOpeningTurn->transcript,
            'rewritten_text'   => 'The Government stands in firm support of this motion. Adolescent mental health is in crisis: the UK Mental Health Foundation documents a 52% surge in clinical anxiety among 13–15 year-olds, with heavy social media use as the strongest independent predictor even after controlling for income and family instability. This is not coincidence — it is mechanism: algorithmic content loops, social comparison, and cyberbullying are engineered features, not side effects. Our proposal is targeted and proportionate — a verified age-gate identical to those already enforced by France and Norway. Governments have always regulated spaces where children gather; the digital commons is no different. The question is not whether to protect minors, but whether we have the political will to do so.',
            'explanation_bullets' => [
                'Added specificity: stated "strongest independent predictor even after controlling for income and family instability" to pre-empt the causation objection.',
                'Named the three harm mechanisms (algorithmic loops, social comparison, cyberbullying) to make the argument more concrete and harder to dismiss.',
                'Strengthened the analogy: compared digital regulation to existing child-safe physical spaces, not just country-level policy examples.',
                'Ended with a rhetorical reframe — shifted the burden of proof back to the Opposition.',
            ],
        ]);

        // ── 4b. Rebuttal ─────────────────────────────────────────
        $rebuttal = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'rebuttal',
            'phase_order' => 2,
        ]);

        Turn::create([
            'round_id'   => $rebuttal->id,
            'speaker'    => 'user',
            'transcript' => 'The Opposition raises the concern about rural and creative youth — we take it seriously, and it only strengthens our case. Precisely because these young people deserve real opportunities, they deserve a moderated, safe digital environment — not one dominated by engagement-maximising algorithms that surface harmful content. Our ban applies to commercial social media platforms specifically. Community forums, educational networks, and supervised school platforms remain fully available. As for the "darker corners" argument: that is a reason to invest in platform-level regulation, not a reason to abandon protection altogether. We maintain that harm reduction requires removing the primary vector.',
            'audio_path' => null,
        ]);

        Turn::create([
            'round_id'   => $rebuttal->id,
            'speaker'    => 'ai',
            'transcript' => 'The Government has now quietly narrowed its motion from a blanket ban to a platform-specific restriction with carve-outs — that is a substantial concession and the House should note it. Furthermore, who defines "commercial social media"? YouTube? WhatsApp family groups? Discord study servers? Every line the Government draws will be contested in court for a decade, during which the harm they describe continues unabated. The Opposition\'s model — algorithmic transparency law, default-off recommendation engines for minors, and mandatory content-harm reporting — achieves the Government\'s stated goal without the definitional chaos and without punishing responsible adolescent users.',
            'audio_path' => null,
        ]);

        // ── 4c. Closing ──────────────────────────────────────────
        $closing = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'closing',
            'phase_order' => 3,
        ]);

        // In Tournament closing, Opposition speaks first (BP closing convention)
        Turn::create([
            'round_id'   => $closing->id,
            'speaker'    => 'ai',
            'transcript' => 'The Opposition closes with this: the Government has failed to establish that a ban is the least-restrictive means to achieve child safety — the gold standard in rights-limiting legislation. Their own rebuttal invented carve-outs that were never in the motion. They have not answered how enforcement works across VPNs and app stores. The Opposition model — algorithmic transparency, age-appropriate design codes, funded digital literacy in schools — already exists in the UK\'s Online Safety Act and Australia\'s draft legislation. We do not need a ban. We need enforcement. The Opposition urges this House to oppose.',
            'audio_path' => null,
        ]);

        Turn::create([
            'round_id'   => $closing->id,
            'speaker'    => 'user',
            'transcript' => 'The Government closes with the fundamental question: when we know a space is causing documented harm to children, do we regulate the harmful elements or do we simply remove children from it until we solve the harder problem? The Opposition\'s algorithmic transparency model is admirable — and has been proposed for fifteen years. In the meantime, a generation has grown up inside the experiment. France has implemented a ban with measurable effect. We do not wait for the perfect policy when the good policy is available today. We urge the House to support.',
            'audio_path' => null,
        ]);

        // ──────────────────────────────────────────────────────────
        // 5. Adjudication
        // ──────────────────────────────────────────────────────────
        Adjudication::create([
            'debate_id'    => $debate->id,
            'matter_score' => 31,
            'manner_score' => 24,
            'method_score' => 22,
            'total_score'  => 77,
            'fallacies'    => [
                [
                    'phase'       => 'opening',
                    'type'        => 'Correlation ≠ Causation',
                    'explanation' => 'You cited the 52% anxiety rise as evidence the ban is necessary, but did not establish a mechanism linking social media to the harm rather than co-occurring factors. Flagged by the Opposition correctly — address this pre-emptively next time.',
                ],
                [
                    'phase'       => 'rebuttal',
                    'type'        => 'Scope Creep / Moving Goalposts',
                    'explanation' => 'You introduced "commercial platform" carve-outs in the Rebuttal that were not part of your Opening model. This weakened your consistency and gave the Opposition a free hit. Define the policy scope fully in the Opening.',
                ],
                [
                    'phase'       => 'closing',
                    'type'        => 'False Dilemma',
                    'explanation' => 'Your closing framing ("remove harmful elements OR remove children") presented only two options. The Opposition\'s middle-ground regulation model was a viable third path that you did not fully refute.',
                ],
            ],
            'feedback_bullets' => [
                '[STRENGTH] Strong empirical grounding in the Opening — the UK Mental Health Foundation citation gave you early credibility and put the Opposition on the back foot.',
                '[STRENGTH] The "duty of care" framing was your most consistent through-line and resonated across all three phases.',
                '[ISSUE] You needed to pre-empt the causation objection in the Opening itself, not wait to be challenged on it in the Rebuttal.',
                '[ISSUE] The Opposition\'s "rural and creative youth" counter-argument was your toughest moment. Your rebuttal was reasonable but could have been sharper — acknowledge the genuine value, then clearly explain why supervised educational platforms are a superior substitute for unregulated commercial feeds.',
                '[ISSUE] Enforcement mechanism was never addressed. A judge will ask "how?" — VPN bypass, app store compliance, parental bypass — you must have an answer ready.',
                '[TIP] Your Closing was your best speech — punchy, emotionally resonant, and ended on a concrete comparator (France). Lead with that energy from the Opening next time.',
                '[TIP] Practice the "minimum necessary intervention" test: for every restriction you propose, proactively justify why a lighter-touch measure would be insufficient.',
            ],
            'verdict' => 'The Government wins a narrow majority on this motion. Matter was strong and well-evidenced. However, method consistency suffered from the mid-debate scope narrowing, and the Opposition\'s enforcement critique was left unresolved. A confident Government team with stronger structural consistency would have won decisively. Score: 77 / 100 — Competent Intermediate performance.',
        ]);

        $this->command->info('TournamentModeSeeder: Created 1 fully resolved tournament debate.');
        $this->command->info("  Motion  : \"{$motion->text_en}\"");
        $this->command->info("  Debate  : {$debate->id}  (session: {$debate->session_id})");
        $this->command->info('  Rounds  : Opening, Rebuttal, Closing (6 turns total)');
        $this->command->info('  Rewrite : 1 turn rewrite on Opening user turn');
        $this->command->info('  Score   : Matter 31 | Manner 24 | Method 22 | Total 77 / 100');
        $this->command->info('  Verdict : Government wins (narrow)');
    }
}

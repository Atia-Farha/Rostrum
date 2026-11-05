<?php

namespace App\Support;

use App\Models\Adjudication;
use App\Models\Debate;
use Illuminate\Support\Collection;

/**
 * The analysis behind the adjudication report.
 *
 * The judge produces a mark, a verdict, a list of fallacies and a list of
 * findings. On its own that is a score card. This class turns it into
 * something a debater can act on, by answering the questions the raw
 * data cannot:
 *
 *   - Which criterion did the round lose points on? (not just which was
 *     lowest — a low score and a low *percentage* are different things
 *     when the criteria have different weights)
 *   - What does that score actually mean? (the band it fell in)
 *   - Where in the round did it happen? (findings anchored to a phase)
 *   - How long did they speak, and did they use the time? (estimated
 *     speaking time against the phase's budget)
 *   - What is the one thing to practise next? (the drill)
 *
 * Everything here is derived. Nothing is invented, and nothing is stored:
 * the class reads the adjudication and the transcript and returns a
 * structure for the report to render.
 */
class ReportAnalysis
{
    /**
     * The tags the judge may put in front of a finding, mapped to the
     * kind of finding they represent.
     *
     * Anything unrecognised becomes a 'note' rather than being dropped —
     * a finding the reader cannot categorise is still a finding.
     */
    private const TAG_KINDS = [
        'STRENGTH' => 'strength',
        'GOOD'     => 'strength',
        'ISSUE'    => 'issue',
        'WARNING'  => 'issue',
        'WEAK'     => 'issue',
        'TIP'      => 'tip',
        'ADVICE'   => 'tip',
        'DRILL'    => 'drill',
        'NOTE'     => 'note',
    ];

    /**
     * The order the kinds are shown in.
     *
     * Strengths come first because a report that opens with what went
     * wrong reads as a mark-sheet; the reader needs to know what to keep
     * doing before they can take in what to change. The drill comes
     * last because it is the thing to do, and it follows from the two.
     */
    private const KIND_ORDER = ['strength', 'issue', 'tip', 'drill', 'note'];

    /**
     * The kind labels, in words a judge would use.
     */
    private const KIND_LABELS = [
        'strength' => 'Strengths',
        'issue'    => 'Areas to work on',
        'tip'      => 'Recommendations',
        'drill'    => 'Practice drill',
        'note'     => 'Further notes',
    ];

    /**
     * The phase names, in the order they are argued.
     */
    private const PHASES = ['opening', 'rebuttal', 'closing'];

    public function __construct(
        private readonly Debate $debate,
        private readonly Adjudication $adjudication,
    ) {
    }

    /**
     * The report's whole analysis, in one structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'grade'      => $this->grade(),
            'criteria'   => $this->criteria(),
            'readout'    => $this->readout(),
            'brief'      => $this->brief(),
            'findings'   => $this->findings(),
            'phases'     => $this->phases(),
            'delivery'   => $this->delivery(),
            'transcript' => $this->transcript(),
            'ladder'     => $this->ladder(),
        ];
    }

    // -------------------------------------------------------------------------
    // The ladder
    // -------------------------------------------------------------------------

    /**
     * The grade bands, with the round's own mark placed on them.
     *
     * A mark on its own says nothing about how near the next band the
     * round came, or how far it is from the bottom. The ladder shows
     * the whole scale with the mark on it, so the reader can see the
     * round in the context of the rubric rather than as one number.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ladder(): array
    {
        $total  = (int) $this->adjudication->total_score;
        $grades = config('debate.rubric.grades', []);

        // The bands run from the top down, which is the order a ladder
        // should be read in: the best band at the top.
        $sorted = collect($grades)->sortByDesc('min')->values();

        $rows = [];

        foreach ($sorted as $index => $grade) {
            $floor = (int) $grade['min'];
            $ceil  = $index === 0 ? 100 : (int) $sorted[$index - 1]['min'];

            $rows[] = [
                'label'    => $grade['label'] ?? 'Ungraded',
                'summary' => $grade['summary'] ?? '',
                'floor'    => $floor,
                'ceiling'  => $ceil,
                'span'     => max(1, $ceil - $floor),
                // The band the mark falls in.
                'current'  => $total >= $floor && $total < $ceil,
                // The mark is above this band — the band was surpassed.
                'passed'   => $total >= $ceil,
                // The band is above the mark — it was not reached.
                'ahead'    => $total < $floor,
                // How far through this band the mark sits, as a
                // percentage of the band's own range. For the band the
                // mark is in, this is the mark's position inside it; for
                // a band below, the band is full; for one above, empty.
                'progress' => $total >= $ceil
                    ? 100
                    : ($total <= $floor ? 0 : (int) round((($total - $floor) / max(1, $ceil - $floor)) * 100)),
                // How many points short of this band's floor the mark
                // is. On the band above the mark this is the gap to
                // close; on the band the mark is in it is how far past
                // the floor it went.
                'gap'      => max(0, $floor - $total),
            ];
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // The brief
    // -------------------------------------------------------------------------

    /**
     * The report in one screen, in plain words.
     *
     * The detail below is thorough, which is what a judge wants — but
     * thoroughness is not the same as comprehensibility. A reader who
     * has just come off the floor does not want to work out what a
     * mark of 77 in the band called "Strong" means, or work out that
     * "Method" was the criterion that cost the most.
     *
     * So the report opens with a brief: four lines of ordinary
     * language that say what happened, where it was won, where it was
     * lost, and what to do about it. Everything after it is the
     * evidence for those four lines, and the reader who wants only the
     * brief never has to scroll.
     *
     * Nothing here is invented. Every claim is derived from the
     * adjudication, the rubric and the transcript.
     *
     * @return array<string, mixed>
     */
    public function brief(): array
    {
        $grade    = $this->grade();
        $criteria = $this->criteria();
        $readout  = $this->readout();
        $delivery = $this->delivery();

        if ($criteria === []) {
            return ['available' => false];
        }

        // The lines are built as statements rather than as fragments,
        // so the panel reads as sentences. A bullet that says "Manner:
        // 80%" is a data point; "Your delivery was the strongest part
        // of the round" is something a reader can act on.
        $lines = [];

        // 1. What the mark means, in the rubric's own word for it.
        $lines[] = [
            'kind'  => 'mark',
            'label' => 'Your mark',
            'text'  => sprintf(
                'You scored %d out of 100 — %s. %s',
                $grade['total'],
                $grade['label'],
                $grade['summary'],
            ),
        ];

        // 2. What was strongest.
        if ($readout['available']) {
            $strongest = $readout['strongest'];

            $lines[] = [
                'kind'  => 'strength',
                'label' => 'What worked',
                'text'  => sprintf(
                    '%s scored highest at %d%% of the marks available for it. %s',
                    $this->subjectCriterion($strongest['label']),
                    (int) $strongest['percent'],
                    $strongest['band_note'],
                ),
            ];

            // 3. What cost the round the most.
            $weakest = $readout['weakest'];

            $lines[] = [
                'kind'  => 'weakness',
                'label' => 'What cost you',
                'text'  => sprintf(
                    '%s scored lowest at %d%%, leaving %d of the %d marks on the table.',
                    $this->subjectCriterion($weakest['label']),
                    (int) $weakest['percent'],
                    (int) $weakest['left'],
                    (int) $weakest['weight'],
                ),
            ];
        }

        // 4. The one thing to change. The judge's own issue comes
        // first, because it is the thing the judge actually said;
        // the time finding is the fallback, and it is only mentioned
        // when it is a real shortfall.
        //
        // The line names the judge's issue as the judge's. The report
        // does not claim to have worked out which flaw cost the round
        // the most — it reports the one the judge flagged first, and
        // the scorecard carries the arithmetic behind the rest.
        $urgent = $readout['urgent'] ?? null;

        if ($urgent !== null) {
            $lines[] = [
                'kind'  => 'action',
                'label' => 'Do this next',
                'text'  => $urgent['phase'] !== null
                    ? sprintf(
                        'Start with the %s. %s',
                        ucfirst($urgent['phase']),
                        $this->actionSentence($urgent['text']),
                    )
                    : sprintf('Start with this. %s', $this->actionSentence($urgent['text'])),
            ];
        } elseif ($delivery['available'] && $delivery['fill'] !== null && $delivery['fill'] < 60) {
            $lines[] = [
                'kind'  => 'action',
                'label' => 'Do this next',
                'text'  => 'Say more. You used only about '
                    . (int) $delivery['fill']
                    . '% of the time you were given, so there is material you have not yet put on the table.',
            ];
        }

        return [
            'available' => true,
            'lines'     => $lines,
            // The key to the two vocabularies in the rest of the
            // report. The reader meets an overall band (Strong, Pass)
            // and a criterion band (Solid, Adequate) in the same
            // document, and nothing on the page says how they relate.
            // One line here saves the reader from working it out.
            'key'       => 'Two scales are used below. Your overall mark falls in a band — ' . $grade['label']
                . '. Each criterion also falls in a band of its own. Every band is shown with the '
                . 'points it starts at, so you can always see the line you reached and the line you did not.',
        ];
    }

    /**
     * A criterion's name, said the way a debater would say it.
     *
     * "Manner" is the judge's word. "Your delivery" is the same thing
     * in the language of the person who has to act on it.
     */
    private function plainCriterion(string $label): string
    {
        return match ($label) {
            'Matter'  => 'arguments and evidence',
            'Manner'  => 'delivery',
            'Method'  => 'structure and rebuttal',
            default   => strtolower($label),
        };
    }

    /**
     * A criterion's name, agreeing with the word that follows it.
     *
     * "Your delivery was..." reads well; "your delivery was..." does
     * not. The brief's sentences are built from a subject and a verb,
     * and the subject has to agree with it.
     */
    private function subjectCriterion(string $label): string
    {
        return match ($label) {
            'Matter'  => 'Your arguments and evidence',
            'Manner'  => 'Your delivery',
            'Method'  => 'Your structure and rebuttal',
            default   => 'Your ' . strtolower($label),
        };
    }

    /**
     * The action in a finding, as an instruction.
     *
     * The judge writes findings as observations — "the mechanism was
     * never stated". A reader at the end of a round needs the
     * instruction, not the observation, and the observation is still
     * there in section 03 with the judge's full words.
     */
    private function actionSentence(string $text): string
    {
        // The finding text is the judge's own. It is trimmed of its
        // trailing full stop so it can sit inside a larger sentence.
        $text = rtrim(trim($text), '.');

        return $text === '' ? '' : $text . '.';
    }

    // -------------------------------------------------------------------------
    // The readout
    // -------------------------------------------------------------------------

    /**
     * What the mark means, in words.
     *
     * A scorecard tells a reader where the points were lost, but not
     * what to do about it. This reads the three criteria together and
     * says which one cost the round the most, which one carried it, and
     * what the single most urgent thing to change is.
     *
     * Everything here is derived from the scores and the findings — no
     * part of it is invented.
     *
     * @return array<string, mixed>
     */
    public function readout(): array
    {
        $criteria = $this->criteria();

        if ($criteria === []) {
            return ['available' => false];
        }

        // The criterion that lost the most ground: the furthest below
        // its own maximum, as a percentage. Comparing the raw scores
        // across criteria of different weights would point at the wrong
        // one — a 22/30 is a better showing than a 22/40.
        //
        // The comparison is on the percentage, not the score, so the
        // criteria are ranked on the same footing.
        $ranked = collect($criteria)->sortByDesc(fn (array $c) => (int) $c['percent']);

        $strongest = $ranked->first();
        $weakest   = $ranked->last();

        $delivery = $this->delivery();

        // The most urgent thing to change is the finding the judge
        // marked as an issue and anchored to a phase — it is a named
        // moment in the round with something to do about it. Failing
        // that, the first issue.
        $urgent = null;

        foreach ($this->findings() as $group) {
            if ($group['kind'] !== 'issue') {
                continue;
            }

            $urgent = $group['items'][0];
            break;
        }

        $sentences = [];

        // The brief at the top of the report already says which
        // criterion was strongest and which was weakest. This section
        // sits under the scorecard, so it says the things the
        // scorecard's numbers imply but do not state: how many marks in
        // total went unclaimed, and what the next band would have
        // needed.
        $left = collect($criteria)->sum(fn (array $c) => (int) $c['left']);

        if ($left > 0) {
            $sentences[] = sprintf(
                'Across the three criteria you left %d of the %d marks on the table.',
                (int) $left,
                (int) collect($criteria)->sum(fn (array $c) => (int) $c['weight']),
            );
        }

        if ($weakest['next_band'] !== null) {
            $sentences[] = sprintf(
                '%s was %d %s short of %s, on a criterion worth %d marks.',
                $weakest['label'],
                (int) $weakest['next_band']['gap'],
                \Illuminate\Support\Str::plural('point', (int) $weakest['next_band']['gap']),
                $weakest['next_band']['label'],
                (int) $weakest['weight'],
            );
        }

        if ($delivery['available'] && $delivery['fill'] !== null && $delivery['fill'] < 60) {
            $sentences[] = sprintf(
                'You used roughly %d%% of the time you were given across the round, so there is material you have not yet put on the table.',
                (int) $delivery['fill'],
            );
        }

        if ($urgent !== null) {
            // This is the judge's own first issue, not the analysis's
            // opinion of which flaw mattered most. It is named as the
            // judge's, so the reader can tell the difference between
            // what was scored and what was noticed.
            $sentences[] = $urgent['phase'] !== null
                ? sprintf('The first thing the judge flagged is in the %s.', ucfirst($urgent['phase']))
                : 'The first thing the judge flagged is the issue above.';
        }

        return [
            'available'  => true,
            'strongest'  => $strongest,
            'weakest'    => $weakest,
            'urgent'     => $urgent,
            'sentences'  => $sentences,
        ];
    }

    // -------------------------------------------------------------------------
    // The mark
    // -------------------------------------------------------------------------

    /**
     * The grade: the total, the band it falls in, and the band it would
     * need to reach the one above.
     *
     * @return array<string, mixed>
     */
    public function grade(): array
    {
        $total  = (int) $this->adjudication->total_score;
        $grades = config('debate.rubric.grades', []);

        // The bands are listed from the top down, so the band is the
        // last one whose floor the total clears. Sorting by the floor
        // and walking down finds the same band without depending on the
        // order the config happens to be written in.
        $sorted = collect($grades)->sortByDesc('min')->values();

        $band = null;
        $next = null;

        foreach ($sorted as $grade) {
            if ($total >= (int) $grade['min']) {
                $band = $grade;
                break;
            }

            // The first band the total has not reached is the one above
            // it — the gap the reader is working towards.
            $next ??= $grade;
        }

        $band ??= [
            'label'   => 'Ungraded',
            'summary' => '',
        ];

        return [
            'total'        => $total,
            'label'        => $band['label'],
            'summary'      => $band['summary'] ?? '',
            'next_label'   => $next['label'] ?? null,
            'next_at'      => $next['min'] ?? null,
            'next_gap'     => $next ? $next['min'] - $total : null,
        ];
    }

    // -------------------------------------------------------------------------
    // The scorecard
    // -------------------------------------------------------------------------

    /**
     * The three criteria, each with its score, its percentage, its band
     * and the band it landed in.
     *
     * The percentage matters as much as the raw score: the criteria have
     * different weights, so a 22/30 on Method is a stronger showing than
     * a 22/40 on Matter, and reading the raw numbers side by side
     * invites exactly that mistake.
     *
     * @return array<int, array<string, mixed>>
     */
    public function criteria(): array
    {
        $criteria = config('debate.rubric.criteria', []);

        $rows = [];

        foreach ($criteria as $key => $criterion) {
            $score  = (int) $this->adjudication->{$key . '_score'};
            $weight = (int) ($criterion['weight'] ?? 0);
            $pct    = $weight > 0 ? (int) round(($score / $weight) * 100) : 0;

            $band = $this->bandFor($criterion['bands'] ?? [], $score);

            $rows[] = [
                'key'      => $key,
                'label'    => $criterion['label'] ?? ucfirst((string) $key),
                // The same criterion in the language of the person who
                // has to act on it. "Manner" is the judge's word;
                // "delivery" is the one the reader uses.
                'plain'    => $this->plainCriterion($criterion['label'] ?? ucfirst((string) $key)),
                'question' => $criterion['question'] ?? '',
                'score'    => $score,
                'weight'   => $weight,
                'percent'  => $pct,
                'band'     => $band['label'] ?? '—',
                'band_note'=> $band['note'] ?? '',
                'meter'    => $weight > 0 ? max(0, min(100, $pct)) : 0,
                // The marks that were available and not taken. This is
                // the number that says how much was on the table, which
                // the percentage alone does not: a criterion worth 40
                // can lose more points than one worth 30 at a higher
                // percentage.
                'left'     => max(0, $weight - $score),
                // The criterion's own scale, with the band the score
                // landed in marked. A reader who is told only that the
                // score was "Solid" has to take the word on trust; a
                // reader who can see that 34 was the line for
                // "Excellent" can see how close the round came.
                'ladder'   => $this->bandLadder($criterion['bands'] ?? [], $band),
                // The band above this one, and how many points it was
                // short by. A score with a "what now" attached to it
                // tells the reader where to aim; a score on its own
                // only tells them where they were.
                'next_band' => $this->nextBand($criterion['bands'] ?? [], $band, $score),
            ];
        }

        return $rows;
    }

    /**
     * The band a score falls in.
     *
     * The bands are listed from the top down, so the first one the score
     * clears is the one it belongs to.
     *
     * @param  array<int, array<string, mixed>>  $bands
     * @return array<string, mixed>|null
     */
    private function bandFor(array $bands, int $score): ?array
    {
        foreach ($bands as $band) {
            if ($score >= (int) ($band['min'] ?? 0)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * A criterion's own scale, with the band the score landed in marked.
     *
     * The report has two scales in it — the overall band the mark falls
     * in, and the band each criterion falls in — and they use different
     * words, which is exactly why they are hard to read. Showing the
     * criterion's own ladder next to the criterion's score makes the
     * mark self-explaining: the reader sees the line they fell short
     * of rather than being told a word.
     *
     * @param  array<int, array<string, mixed>>  $bands
     * @param  array<string, mixed>|null  $current
     * @return array<int, array<string, mixed>>
     */
    private function bandLadder(array $bands, ?array $current): array
    {
        $rows = [];

        foreach ($bands as $band) {
            $rows[] = [
                'label'   => $band['label'] ?? '',
                'note'    => $band['note'] ?? '',
                'min'     => (int) ($band['min'] ?? 0),
                'current' => $current !== null
                    && ($current['label'] ?? null) === ($band['label'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * The band above the one the score landed in, and what it would take.
     *
     * "Solid, and 3 points short of Excellent" is a sentence a reader
     * can act on. "Solid" on its own is a word they have to trust.
     *
     * @param  array<int, array<string, mixed>>  $bands
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>|null
     */
    private function nextBand(array $bands, ?array $current, int $score): ?array
    {
        if ($current === null) {
            return null;
        }

        foreach ($bands as $band) {
            if (($band['label'] ?? null) === ($current['label'] ?? null)) {
                continue;
            }

            // The bands run from the top down, so the first one that is
            // not the current band and sits above it is the next one.
            if ((int) ($band['min'] ?? 0) > $score) {
                return [
                    'label' => $band['label'] ?? '',
                    'at'    => (int) ($band['min'] ?? 0),
                    'gap'   => max(0, (int) ($band['min'] ?? 0) - $score),
                ];
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // The findings
    // -------------------------------------------------------------------------

    /**
     * The judge's findings, grouped by kind and each one anchored to the
     * phase it is about.
     *
     * A finding that says "your Opening needed a mechanism" is only
     * useful if the reader knows which speech to reopen. The judge
     * usually names the phase in the text; where it does not, the
     * finding is left unanchored rather than guessed at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findings(): array
    {
        $groups = [];

        foreach ($this->adjudication->feedback_bullets ?? [] as $index => $bullet) {
            $parsed = $this->parseBullet((string) $bullet, $index);

            $groups[$parsed['kind']][] = $parsed;
        }

        // The judge is asked to end with a practice plan. Where that
        // bullet carries no [DRILL] tag — an adjudication made before
        // the tag existed — the closing recommendation is promoted to
        // the drill. The prompt's contract puts the practice plan last,
        // so the last recommendation is the practice plan.
        if (empty($groups['drill']) && ! empty($groups['tip'])) {
            $last = array_pop($groups['tip']);

            $last['kind'] = 'drill';
            $last['tag']  = $last['tag'] ?? 'DRILL';

            $groups['drill'][] = $last;
        }

        $ordered = [];

        foreach (self::KIND_ORDER as $kind) {
            if (empty($groups[$kind])) {
                continue;
            }

            $ordered[] = [
                'kind'  => $kind,
                'label' => self::KIND_LABELS[$kind],
                'items' => $groups[$kind],
            ];
        }

        return $ordered;
    }

    /**
     * One finding, taken apart.
     *
     * @return array<string, mixed>
     */
    private function parseBullet(string $bullet, int $index): array
    {
        $tag   = null;
        $kind  = 'note';
        $clean = $bullet;

        // A leading [TAG] is the judge's own label for the finding.
        if (preg_match('/^\[([A-Z]+)\]\s*/u', $bullet, $matches) === 1) {
            $tag   = $matches[1];
            $kind  = self::TAG_KINDS[strtoupper($tag)] ?? 'note';
            $clean = ltrim(substr($bullet, strlen($matches[0])));
        }

        // The judge sometimes opens the finding with the words the
        // speaker used. Pulling the quote out lets the report set the
        // finding beside the speech it is about.
        $quote      = $this->quoteIn($clean);
        $quotePhase = $quote !== null ? $this->phaseOfQuote($quote) : null;

        // Whether the judge's sentence ended with a full stop. Lifting
        // a quote out of the middle of a sentence takes the closing
        // punctuation with it, and a finding that runs off the end of
        // its own sentence reads as a truncation rather than as prose.
        $endedWithStop = (bool) preg_match('/[.!?]["”]?$/u', $clean);

        // The quote is removed from the body — it is shown as a quote,
        // and leaving it in the sentence as well would print it twice.
        if ($quote !== null) {
            $clean = $this->removeQuote($clean, $quote);
        }

        // A speech that was never made leaves a hole in the transcript,
        // and the judge sometimes quotes the hole rather than the speech.
        // The placeholder is not a finding; it is an absence of one.
        $clean = preg_replace(
            '/^You said\s*["\']?\[No speech provided\]["\']?\s*[\x{2014}\x{2013}\-:]\s*/u',
            '',
            $clean
        ) ?? $clean;

        // The phase is read from the body, once the quote has been
        // lifted out — a quoted phrase can mention a phase the finding
        // is not actually about. Where the judge named no phase, the
        // phase the quote was spoken in is a better answer than none.
        $phase = $this->phaseNamedIn($clean) ?? $quotePhase;

        // The first letter is capitalised so a body that has had its
        // quote lifted out still reads as a sentence.
        $clean = mb_strtoupper(mb_substr($clean, 0, 1)) . mb_substr($clean, 1);

        // The sentence is closed. A quote lifted out of the middle of
        // it took the full stop with it, so it is put back — but only
        // if the judge ended the finding with one in the first place.
        if ($endedWithStop && ! preg_match('/[.!?]["”]?$/u', $clean)) {
            $clean .= '.';
        }

        return [
            'index' => $index,
            'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'tag'   => $tag,
            'kind'  => $kind,
            'phase' => $phase,
            'quote' => $quote,
            'text'  => $clean,
        ];
    }

    /**
     * Removes a quoted phrase from a finding, along with the quotes and
     * the word that introduced it.
     *
     * Removing the phrase alone would leave the quotation marks behind,
     * and the body would read `The "" framing was your most consistent
     * through-line` — a sentence with a hole in it. The word before the
     * quote usually introduces it ("the", "your"), so it goes too.
     */
    private function removeQuote(string $text, string $quote): string
    {
        $patterns = [
            '/["“”]?' . preg_quote($quote, '/') . '["“”]?/u',
            '/\b(?:the|your|his|her|their|its)\s+(?:["“”]?' . preg_quote($quote, '/') . '["“”]?)/ui',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text, 1) ?? $text;
        }

        // Whatever is left is tidied: the gap the quote left behind, and
        // the punctuation that used to sit next to it.
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;
        $text = trim($text);
        $text = ltrim($text, " \t\n\r—–-:,.;" );
        $text = rtrim($text, " \t\n\r—–-:.,;");

        return $text;
    }

    /**
     * The phase a finding names, if it names one.
     *
     * Two rules make this reliable. The phase name must be
     * capitalised, because a judge writes "your Closing" when they mean
     * the phase and "your rebuttal was reasonable" when they mean the
     * act — the same word, two meanings, and only the capitalisation
     * separates them.
     *
     * And when a finding names two phases, the earlier one is the
     * subject. "Your Closing was your best speech — lead with that
     * energy from the Opening next time" is a note on the Closing; "you
     * needed to pre-empt it in the Opening itself, not the Rebuttal" is
     * a note on the Opening.
     */
    private function phaseNamedIn(string $text): ?string
    {
        $best = null;
        $bestAt = null;

        foreach (self::PHASES as $phase) {
            $capitalised = ucfirst($phase);

            if (preg_match('/\b' . $capitalised . '\b/', $text, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $at = $matches[0][1];

                if ($bestAt === null || $at < $bestAt) {
                    $bestAt = $at;
                    $best   = $phase;
                }
            }
        }

        return $best;
    }

    /**
     * The passage of the speaker's own words that a finding quotes.
     *
     * Only a phrase the speaker actually said counts. The judge quotes
     * the opponent, or a phrase that does not exist in the transcript,
     * often enough that an unverified attribution would put words in the
     * speaker's mouth. A quote that cannot be found in the speaker's own
     * turns is not shown as one.
     */
    private function quoteIn(string $text): ?string
    {
        if (preg_match('/["“”]([^"“”]{4,120})["“”]/u', $text, $matches) !== 1) {
            return null;
        }

        $candidate = trim($matches[1]);

        // A question mark inside the quote means it is the judge
        // quoting a question, not the speaker's words.
        if (str_contains($candidate, '?')) {
            return null;
        }

        return $this->wasSaidBySpeaker($candidate) ? $candidate : null;
    }

    /**
     * Whether a phrase appears in the speaker's own turns.
     *
     * The comparison is case-insensitive and ignores the punctuation that
     * a speaker and a judge might not agree on, so a phrase that was
     * said still matches when the judge changed the capitalisation.
     */
    private function wasSaidBySpeaker(string $phrase): bool
    {
        return $this->phaseOfQuote($phrase) !== null;
    }

    /**
     * The phase in which the speaker said a phrase.
     *
     * A phrase can appear in more than one speech — a through-line the
     * speaker returns to, say — so the first one is taken: the earliest
     * speech it appears in is the one the reader should hear first.
     */
    private function phaseOfQuote(string $phrase): ?string
    {
        $needle = $this->normalise($phrase);

        if ($needle === '') {
            return null;
        }

        foreach ($this->debate->rounds as $round) {
            foreach ($round->turns as $turn) {
                if ($turn->speaker !== 'user') {
                    continue;
                }

                if (str_contains($this->normalise($turn->transcript), $needle)) {
                    return $round->phase ?? 'sparring';
                }
            }
        }

        return null;
    }

    /**
     * A string reduced to the characters that carry its meaning.
     */
    private function normalise(string $text): string
    {
        $text = mb_strtolower($text);

        // Drop the punctuation, and keep only letters, digits and
        // spaces — so a quote matches whatever punctuation the two
        // sentences happen to use.
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    // -------------------------------------------------------------------------
    // The round, phase by phase
    // -------------------------------------------------------------------------

    /**
     * Each phase of the round, with what the speaker said in it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function phases(): array
    {
        $phases = [];

        foreach ($this->debate->rounds as $round) {
            $phase = $round->phase ?? 'sparring';

            $userTurn = $round->turns->firstWhere('speaker', 'user');
            $aiTurn   = $round->turns->firstWhere('speaker', 'ai');

            $userWords = $userTurn ? $this->wordCount($userTurn->transcript) : 0;
            $aiWords   = $aiTurn ? $this->wordCount($aiTurn->transcript) : 0;

            $budget = (int) (config('debate.phase_duration')[$phase] ?? 0);

            $phases[] = [
                'key'       => $phase,
                'label'     => $this->phaseLabel($phase),
                'order'     => (int) $round->phase_order,
                'words'     => $userWords,
                'ai_words'  => $aiWords,
                'seconds'   => $this->estimateSeconds($userWords),
                'budget'    => $budget,
                // The share of the budget used. It is not capped at a
                // hundred: a phase that overran its time is reported
                // as an overrun, and a hundred per cent would say the
                // time was used exactly, which is the opposite.
                'fill'      => $budget > 0
                    ? max(0, (int) round(($this->estimateSeconds($userWords) / $budget) * 100))
                    : null,
                'transcript' => $userTurn?->transcript ?? '',
                // The whole exchange, so a judge can see what the speech
                // was answered against and not only the speech itself.
                'turns'     => $round->turns->map(fn ($turn) => [
                    'speaker'    => $turn->speaker,
                    'side'       => $turn->speaker === 'user'
                        ? ucfirst($this->debate->user_side)
                        : ucfirst($this->debate->aiSide()),
                    'transcript' => $turn->transcript,
                    'excerpt'    => $this->excerpt($turn->transcript),
                    'words'      => $this->wordCount($turn->transcript),
                ])->values()->all(),
            ];
        }

        // The note is added once the phase is complete, because it
        // reads from the phase's own budget and time.
        foreach ($phases as $i => $phase) {
            $phases[$i]['note'] = $this->phaseNote($phase);
        }

        return $phases;
    }

    /**
     * The display name of a phase.
     */
    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'opening'  => 'Opening',
            'rebuttal' => 'Rebuttal',
            'closing'  => 'Closing',
            'sparring' => 'Sparring',
            default    => ucfirst($phase),
        };
    }

    /**
     * The opening of a speech, for the round overview.
     *
     * Section 05 is an analysis of the round and section 06 holds the
     * verbatim record, so the two must not print the same text. The
     * overview shows the start of each speech — enough to recognise
     * it and to judge whether the framing landed — and the record
     * carries the whole of it.
     */
    private function excerpt(string $transcript): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $transcript) ?? $transcript);

        if ($text === '') {
            return '';
        }

        // Cut on the end of the first sentence — a full stop, a question
        // mark or an exclamation, so the excerpt never ends mid-clause.
        if (preg_match('/^(.{40,320}?[.?!])\s/u', $text, $matches) === 1) {
            return $matches[1];
        }

        // No sentence end in the opening — a speech that starts with a
        // claim and never punctuates it. Cut on a word boundary.
        if (mb_strlen($text) > 220) {
            $cut = mb_substr($text, 0, 220);
            $at  = mb_strrpos($cut, ' ');

            return rtrim($at !== false ? mb_substr($cut, 0, $at) : $cut, ' ,;:') . '…';
        }

        return $text;
    }

    /**
     * What the time used in a phase means.
     *
     * A percentage on its own is a number the reader has to interpret.
     * The same figure means a different thing depending on the phase:
     * an answer that used a quarter of the Rebuttal was answered, but
     * one that used a quarter of the Opening was never developed.
     *
     * @return array<string, mixed>
     */
    private function phaseNote(array $phase): array
    {
        $fill   = $phase['fill'];
        $budget = $phase['budget'];

        if ($budget <= 0 || $fill === null) {
            return ['tone' => 'none', 'text' => ''];
        }

        // The opening has room to develop; the closing has to land a
        // case in the time it has. A short answer is judged against
        // what that phase was for. The claim is kept to what the
        // number supports: the speech was short, not that it did not
        // happen.
        $shortfall = match ($phase['key']) {
            'opening'  => 'The case was left undeveloped — there was room on the clock for a mechanism and a warrant.',
            'rebuttal' => 'The answer to the opposition was left unfinished — there was room to take the case apart in full.',
            'closing'  => 'The case was not landed — there was room on the clock for the last thing the House needed to hear.',
            default    => 'There was material on the table that never reached the House.',
        };

        $overrun = match ($phase['key']) {
            'opening'  => 'The opening ran long, which leaves the rebuttal and the closing with less to work with.',
            'rebuttal' => 'The rebuttal ran long, which is time taken from the closing.',
            'closing'  => 'The closing ran long, which usually means the case was not finished.',
            default    => 'The speech ran past the time allowed for the phase.',
        };

        return match (true) {
            $fill < 55  => ['tone' => 'short', 'text' => $shortfall],
            $fill > 105 => ['tone' => 'long',  'text' => $overrun],
            default     => ['tone' => 'ok',    'text' => 'The time allowed for this phase was used, without overrunning it.'],
        };
    }

    // -------------------------------------------------------------------------
    // Delivery
    // -------------------------------------------------------------------------

    /**
     * How much the speaker used the time they had.
     *
     * A phase answered in forty seconds out of a three-minute budget is
     * a fact about the round that the mark alone does not carry, and it
     * is one of the most actionable things in the whole report.
     *
     * @return array<string, mixed>
     */
    public function delivery(): array
    {
        $phases = $this->phases();

        $budgeted = collect($phases)->filter(fn ($p) => $p['budget'] > 0);

        if ($budgeted->isEmpty()) {
            return [
                'available' => false,
                'phases'    => [],
            ];
        }

        $rows = [];

        foreach ($budgeted as $phase) {
            $rows[] = [
                'key'     => $phase['key'],
                'label'   => $phase['label'],
                'seconds' => $phase['seconds'],
                'budget'  => $phase['budget'],
                'fill'    => $phase['fill'],
                'short'   => $phase['fill'] !== null && $phase['fill'] < 55,
                'long'    => $phase['fill'] !== null && $phase['fill'] > 105,
            ];
        }

        $totalWords = collect($phases)->sum('words');
        $totalTime  = collect($phases)->sum('seconds');
        $totalBudget = $budgeted->sum('budget');

        return [
            'available'   => true,
            'phases'      => $rows,
            'total_words' => $totalWords,
            'total_time'  => $totalTime,
            'total_budget'=> $totalBudget,
            'fill'        => $totalBudget > 0
                ? max(0, (int) round(($totalTime / $totalBudget) * 100))
                : null,
            'wpm'         => config('debate.words_per_minute', 140),
        ];
    }

    // -------------------------------------------------------------------------
    // The transcript
    // -------------------------------------------------------------------------

    /**
     * Every turn of the round, in order, for the record section.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transcript(): array
    {
        $rows = [];

        foreach ($this->debate->rounds as $round) {
            foreach ($round->turns as $turn) {
                $rows[] = [
                    'phase'      => $this->phaseLabel($round->phase ?? 'sparring'),
                    'speaker'    => $turn->speaker,
                    'side'       => $turn->speaker === 'user'
                        ? ucfirst($this->debate->user_side)
                        : ucfirst($this->debate->aiSide()),
                    'transcript' => $turn->transcript,
                    'words'      => $this->wordCount($turn->transcript),
                ];
            }
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * The number of words in a piece of text.
     *
     * Bangla is written without spaces between words, so a whitespace
     * count would report one word for a whole paragraph. The count
     * falls back to characters for a script that does not separate its
     * words, scaled by the average characters per Bangla word.
     */
    private function wordCount(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $count = count(array_filter($words, fn ($w) => $w !== ''));

        // Bangla does not space its words, so a "word" is counted by
        // its length instead. The factor is the average length of a
        // Bangla word, which is roughly five characters.
        if ($this->debate->language === 'bn') {
            return (int) round(mb_strlen(preg_replace('/\s+/u', '', $text) ?? '') / 5);
        }

        return $count;
    }

    /**
     * The estimated seconds a piece of text takes to say.
     */
    private function estimateSeconds(int $words): int
    {
        $wpm = max(1, (int) config('debate.words_per_minute', 140));

        return (int) round(($words / $wpm) * 60);
    }

    /**
     * A duration, in words.
     */
    public static function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $rest    = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $rest);
    }
}

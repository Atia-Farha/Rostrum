@extends('layouts.app')

@section('title', 'Adjudication Report — Rostrum')

{{--
    The adjudication report.

    A report is a document, not a dashboard. It has a subject, a set of
    findings, and a conclusion. The reader arrives in one of two roles:

        a debater, who has just come off the floor and wants to know
        what to change before the next round;

        a judge, who wants to see how the mark was reached and whether
        they agree with it.

    Both readers want the same thing: the mark, the reasoning behind it,
    and the specific thing to do next. So the document is arranged in
    that order, and every section is numbered so that it can be
    referred to afterwards.

        01  Verdict         the mark and the words for it
        02  Scorecard       where the points went, criterion by criterion
        03  Findings        what the judge saw, grouped by kind
        04  Reasoning       the logical errors, and what to do about each
        05  The round       phase by phase, and the time it took
        06  The record      the full transcript, for reference

    The contents panel beside the report is what makes it a document
    rather than a long page: the reader sees the shape of the argument
    before reading any of it.
--}}

@section('content')

@php
    $adj        = $debate->adjudication;
    $motionText = $debate->motion->textFor($debate->language);
    $isBn       = $debate->language === 'bn';
    $fontStyle  = $isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';

    $grade    = $analysis['grade'];
    $criteria = $analysis['criteria'];
    $readout  = $analysis['readout'];
    $brief    = $analysis['brief'];
    $findings = $analysis['findings'];
    $phases   = $analysis['phases'];
    $delivery = $analysis['delivery'];
    $record   = $analysis['transcript'];
    $ladder   = $analysis['ladder'];

    // The drill is the last finding of the report's last group — the
    // practice plan the judge closes with. It gets its own panel
    // because it is an instruction, not an observation.
    $drill = null;
    $otherGroups = [];

    foreach ($findings as $group) {
        if ($group['kind'] === 'drill') {
            $drill = $group['items'][0] ?? null;
            continue;
        }

        $otherGroups[] = $group;
    }

    // The grade's colour comes from the band it falls in. The colour is
    // a reinforcement — the band's name is always written beside it.
    $gradeTone = match ($grade['label']) {
        'Distinction' => '#22c55e',
        'Strong'      => '#38bdf8',
        'Pass'        => '#f59e0b',
        default       => '#ef4444',
    };

    // The sections that go in the contents panel. The ones with
    // nothing to show are left out rather than listed and empty.
    $sections = [];

    if ($adj) {
        if ($brief['available']) {
            $sections[] = ['num' => '', 'id' => 'brief', 'label' => 'The short version'];
        }

        $sections[] = ['num' => '01', 'id' => 'verdict',  'label' => 'Verdict'];
        $sections[] = ['num' => '02', 'id' => 'scorecard', 'label' => 'Scorecard'];

        if (count($otherGroups) > 0) {
            $sections[] = ['num' => '03', 'id' => 'findings', 'label' => 'Findings'];
        }

        if (! empty($adj->fallacies)) {
            $sections[] = ['num' => '04', 'id' => 'reasoning', 'label' => 'Reasoning errors'];
        }

        $sections[] = ['num' => '05', 'id' => 'round',    'label' => 'The round'];
        $sections[] = ['num' => '06', 'id' => 'record',   'label' => 'The record'];
    }
@endphp

<style>
    /* The report keeps a print stylesheet — it is a document that gets
       exported as PDF, so it has to survive on paper.

       The print rules do two things: they take the chrome off the
       page, and they stop the document's parts from being split
       across a page break. A finding cut in half across two sheets of
       paper is worse than a finding that has been moved whole. */
    @media print {
        @page {
            margin: 0;
            size: auto;
        }

        body {
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0.5in !important;
        }

        .app-nav, .app-side, .app-side-bar,
        .skip-link, .rpt-toolbar, .rpt-toc, .rpt-footer,
        .rpt-record-tools, .toast-container {
            display: none !important;
        }

        /* A closed disclosure does not print its contents, and the
           transcript is the appendix the reader asked for. Every speech
           is opened before the page is sent to the printer. */
        details[open] > summary,
        details > summary {
            list-style: none !important;
        }

        .rpt-turn-summary {
            border-bottom: 1px solid #cbd5e1 !important;
        }

        .rpt-turn-toggle {
            display: none !important;
        }

        body.has-side #main-content {
            padding-left: 0 !important;
        }

        #main-content { padding-top: 0 !important; }

        .rpt {
            max-width: 100% !important;
            padding: 0 !important;
        }

        .rpt-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }

        /* Every panel becomes a bordered block on white. */
        .rpt-section,
        .rpt-cover,
        .rpt-brief,
        .rpt-facts,
        .rpt-drill,
        .rpt-clear {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rpt-brief {
            border-left: 3px solid #4338ca !important;
        }

        .rpt-brief-title { color: #4338ca !important; }

        .rpt-brief-label { color: #475569 !important; }

        .rpt-brief-text { color: #0f172a !important; }

        .rpt-brief-key {
            color: #475569 !important;
            border-top-color: #cbd5e1 !important;
        }

        .rpt-section-head { border-bottom-color: #cbd5e1 !important; }

        /* The inks. Everything that carries text moves to a dark grey
           that reads on paper, and the accent colours are darkened so
           they do not print as pale smudges. */
        .rpt-cover-title, .rpt-section-title, .rpt-criterion-name,
        .rpt-mark-value, .rpt-phase-name, .rpt-turn-side,
        .rpt-fact-value, .rpt-drill-text, .rpt-quote,
        .rpt-prose--lead, .rpt-readout p, .rpt-ladder-label,
        .rpt-ladder-mark, .rpt-grade-summary {
            color: #0f172a !important;
        }

        .rpt-prose, .rpt-item-text, .rpt-band-note, .rpt-phase-text,
        .rpt-turn-text, .rpt-fallacy-text, .rpt-fact-label,
        .rpt-cover-meta, .rpt-section-note, .rpt-phase-stats,
        .rpt-time-note, .rpt-card caption, .rpt-phase-who,
        .rpt-turn-meta, .rpt-ladder-range, .rpt-ladder-note,
        .rpt-ladder-foot, .rpt-section-gloss {
            color: #475569 !important;
        }

        .rpt-section-num, .rpt-readout-title {
            color: #4338ca !important;
        }

        .rpt-fallacy-type { color: #b45309 !important; }

        .rpt-tag {
            border-color: #94a3b8 !important;
            background: #f1f5f9 !important;
            color: #334155 !important;
        }

        /* The criterion ladders print as a plain list, with the band
           the score fell in still marked. */
        .rpt-mini-band {
            color: #475569 !important;
            background: #f8fafc !important;
        }

        .rpt-mini-band.is-current {
            color: #0f172a !important;
            background: #eef2ff !important;
            box-shadow: inset 2px 0 0 #4338ca !important;
        }

        .rpt-mini-gap { color: #4338ca !important; }

        /* The bars are the one thing that carries information by
           position alone, so they are printed in ink rather than in
           the accent colours. */
        .rpt-bar, .rpt-time-bar, .rpt-ladder-bar {
            background: #e2e8f0 !important;
            border: 1px solid #cbd5e1;
        }

        .rpt-bar-fill, .rpt-time-fill, .rpt-ladder-fill {
            background: #475569 !important;
        }

        .rpt-quote {
            background: #f8fafc !important;
            border-left-color: #94a3b8 !important;
        }

        .rpt-readout {
            background: #f8fafc !important;
            border-left-color: #4338ca !important;
        }

        .rpt-drill {
            background: #fffbeb !important;
            border-color: #d97706 !important;
        }

        .rpt-drill-title { color: #b45309 !important; }

        /* The parts of the document that must not be broken up. */
        .rpt-item, .rpt-fallacy, .rpt-phase-row, .rpt-turn,
        .rpt-readout, .rpt-drill, .rpt-cover, .rpt-verb {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rpt-section-head {
            break-after: avoid !important;
            page-break-after: avoid !important;
        }
    }
</style>

<div class="rpt">

    {{-- ── Toolbar ────────────────────────────────────────────────────── --}}
    <div class="rpt-toolbar">
        <a href="{{ route('debates.show', $debate->id) }}" class="surf-btn surf-btn-outline surf-btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Arena
        </a>

        <div class="rpt-toolbar-actions">
            <button type="button" class="surf-btn surf-btn-secondary surf-btn-sm" id="rpt-copy" onclick="copyTranscript(@js(route('debates.transcript', $debate->id)), this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                <span data-copy-label>Copy Transcript</span>
            </button>
            <button type="button" class="surf-btn surf-btn-primary surf-btn-sm" id="btn-download-pdf" onclick="exportPDF()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
                Export PDF
            </button>
        </div>
    </div>

    {{-- ── The frame ──────────────────────────────────────────────────── --}}
    <div class="rpt-grid">

        {{-- The report itself --}}
        <div class="rpt-main">

            {{-- ── The cover ───────────────────────────────────────────── --}}
            <div class="rpt-cover">
                <div class="rpt-cover-body">
                    <span class="rpt-cover-eyebrow">Official Adjudication Report</span>
                    <h1 class="rpt-cover-title" style="{{ $fontStyle }}">{{ $motionText }}</h1>

                    <div class="rpt-cover-meta">
                        <span><strong>{{ ucfirst($debate->user_side) }}</strong> argued</span>
                        <span>against <strong>{{ $debate->persona->name }}</strong></span>
                        <span>{{ str_replace('_', ' ', $debate->difficulty) }}</span>
                        <span>{{ ucfirst($debate->mode) }} format</span>
                        <span>Adjudicated {{ $debate->updated_at->format('d M Y') }}</span>
                    </div>
                </div>

                @if ($adj)
                    <div class="rpt-verdict">
                        {{-- The mark, and the words that go with it. --}}
                        <div class="rpt-mark">
                            <span class="rpt-mark-value">{{ $grade['total'] }}</span>
                            <span class="rpt-mark-denom">out of 100</span>
                            <span class="rpt-mark-band"
                                  style="color: {{ $gradeTone }}; background: {{ $gradeTone }}18; border-color: {{ $gradeTone }}40;">
                                {{ $grade['label'] }}
                            </span>
                        </div>

                        {{-- The cover carries the headline: the mark, the band
                             it fell in, and the distance to the band above.
                             The one line that names the band is not
                             repeated here — the brief underneath says it,
                             and a sentence that appears twice on a page
                             reads as a printing error rather than as
                             emphasis. --}}
                        <div class="rpt-verdict-body">
                            @if ($grade['next_gap'] !== null)
                                <p class="rpt-gap">
                                    <strong>{{ $grade['next_gap'] }} more {{ \Illuminate\Support\Str::plural('point', $grade['next_gap']) }}</strong>
                                    reaches {{ $grade['next_label'] }}.
                                </p>
                            @else
                                <p class="rpt-grade-summary">{{ $grade['summary'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if ($adj)

            {{-- ── The brief ──────────────────────────────────────────── --}}
            {{-- The report in one screen, in plain words. Everything below
                 this panel is the evidence for these four lines. A reader
                 who wants only the answer does not have to scroll, and a
                 reader who wants the reasoning has it all in order. --}}
            @if ($brief['available'])
                <section class="rpt-brief" id="brief" aria-labelledby="rpt-brief-title">
                    <h2 class="rpt-brief-title" id="rpt-brief-title">
                        The short version
                    </h2>

                    <dl class="rpt-brief-list">
                        @foreach ($brief['lines'] as $line)
                            <div class="rpt-brief-line rpt-brief-line--{{ $line['kind'] }}">
                                <dt class="rpt-brief-label">{{ $line['label'] }}</dt>
                                <dd class="rpt-brief-text" style="{{ $fontStyle }}">{{ $line['text'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- The key to the two band vocabularies the report
                         uses. Without it the reader meets "Strong" in
                         one table and "Solid" in the next and has to
                         work out for themselves that they are
                         different scales. --}}
                    @if (! empty($brief['key']))
                        <p class="rpt-brief-key" style="{{ $fontStyle }}">{{ $brief['key'] }}</p>
                    @endif
                </section>
            @endif

            {{-- ── 01 · Verdict ───────────────────────────────────────── --}}
            <section class="rpt-section" id="verdict" aria-labelledby="rpt-verdict-title">
                <div class="rpt-section-head">
                    <h2 class="rpt-section-title" id="rpt-verdict-title">
                        <span class="rpt-section-num" aria-hidden="true">01</span>
                        Verdict
                    </h2>
                    <p class="rpt-section-note">
                        The judge's own reasoning, and the mark's place on the scale.
                    </p>
                </div>

                <div class="rpt-section-body">
                    {{-- The judge's own words. The cover carries the mark;
                         this carries the reasoning, which is worth reading
                         properly rather than skimming beside a number. --}}
                    <p class="rpt-prose rpt-prose--lead" style="{{ $fontStyle }}">{{ $adj->verdict }}</p>

                    {{-- The scale the mark sits on. --}}
                    <div class="rpt-ladder">
                        <p class="rpt-ladder-title">Where the mark sits</p>

                        <ol class="rpt-ladder-list">
                            @foreach ($ladder as $band)
                                <li class="rpt-ladder-row{{ $band['current'] ? ' is-current' : '' }}{{ $band['passed'] ? ' is-passed' : '' }}{{ $band['ahead'] ? ' is-ahead' : '' }}">
                                    <span class="rpt-ladder-label">{{ $band['label'] }}</span>
                                    <span class="rpt-ladder-range">{{ $band['floor'] }}–{{ $band['ceiling'] }}</span>
                                    <span class="rpt-ladder-bar" aria-hidden="true">
                                        <span class="rpt-ladder-fill" style="width: {{ $band['progress'] }}%;"></span>
                                    </span>
                                    @if ($band['current'])
                                        <span class="rpt-ladder-mark">
                                            {{ $grade['total'] }}
                                            <span class="surf-sr-only">— your mark</span>
                                        </span>
                                    @elseif ($band['ahead'])
                                        <span class="rpt-ladder-note">
                                            {{ $band['gap'] }} {{ \Illuminate\Support\Str::plural('point', $band['gap']) }} to go
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </section>

            {{-- ── 02 · Scorecard ──────────────────────────────────────── --}}
            <section class="rpt-section" id="scorecard" aria-labelledby="rpt-scorecard-title">
                <div class="rpt-section-head">
                    <h2 class="rpt-section-title" id="rpt-scorecard-title">
                        <span class="rpt-section-num" aria-hidden="true">02</span>
                        Scorecard
                    </h2>
                    <p class="rpt-section-note">
                        Each criterion as a share of its own maximum, with the band it fell in.
                        <span class="rpt-section-gloss">
                            Matter is your arguments and evidence; Manner is your delivery;
                            Method is your structure and rebuttal.
                        </span>
                    </p>
                </div>

                <div class="rpt-section-body">
                    <table class="rpt-card">
                        <caption>
                            The judge scored the round against three criteria. The percentage is
                            the score as a share of that criterion's own maximum, so the three
                            rows can be compared with each other.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Criterion</th>
                                <th scope="col">Score</th>
                                <th scope="col">Band</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($criteria as $criterion)
                                <tr>
                                    <td class="rpt-criterion">
                                        <div class="rpt-criterion-name">{{ $criterion['label'] }}</div>
                                        <div class="rpt-criterion-note">{{ $criterion['question'] }}</div>
                                    </td>
                                    <td data-label="Score">
                                        <div class="rpt-score">
                                            <span class="rpt-score-value">
                                                {{ $criterion['score'] }}<span>/{{ $criterion['weight'] }}</span>
                                            </span>
                                            <span class="rpt-score-pct">{{ $criterion['percent'] }}% of maximum</span>
                                            <span class="rpt-bar">
                                                <span class="rpt-bar-fill rpt-bar-fill--{{ $criterion['key'] }}"
                                                      style="width: {{ $criterion['meter'] }}%;"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="rpt-band" data-label="Band">
                                        <span class="rpt-band-label">{{ $criterion['band'] }}</span>
                                        <p class="rpt-band-note">{{ $criterion['band_note'] }}</p>

                                        {{-- The criterion's own scale, with the
                                             band it landed in marked. The
                                             reader can see the line they
                                             fell short of instead of
                                             being asked to trust a word. --}}
                                        <ul class="rpt-mini-ladder">
                                            @foreach ($criterion['ladder'] as $band)
                                                <li class="rpt-mini-band{{ $band['current'] ? ' is-current' : '' }}">
                                                    <span class="rpt-mini-band-label">{{ $band['label'] }}</span>
                                                    <span class="rpt-mini-band-at">{{ $band['min'] }}+</span>
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if ($criterion['next_band'])
                                            <p class="rpt-mini-gap">
                                                {{ $criterion['next_band']['gap'] }} {{ \Illuminate\Support\Str::plural('point', $criterion['next_band']['gap']) }}
                                                short of {{ $criterion['next_band']['label'] }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($readout['available'])
                        {{-- What the mark means, said in words. --}}
                        <div class="rpt-readout">
                            <p class="rpt-readout-title">What this means</p>
                            @foreach ($readout['sentences'] as $sentence)
                                <p>{{ $sentence }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- ── 03 · Findings ───────────────────────────────────────── --}}
            @if (count($otherGroups) > 0)
                <section class="rpt-section" id="findings" aria-labelledby="rpt-findings-title">
                    <div class="rpt-section-head">
                        <h2 class="rpt-section-title" id="rpt-findings-title">
                            <span class="rpt-section-num" aria-hidden="true">03</span>
                            Findings
                        </h2>
                        <p class="rpt-section-note">
                            {{ collect($otherGroups)->sum(fn ($g) => count($g['items'])) }} observations from the judge, grouped by kind.
                        </p>
                    </div>

                    <div class="rpt-section-body">
                        @foreach ($otherGroups as $group)
                            <div class="rpt-group">
                                <h3 class="rpt-group-head">
                                    {{ $group['label'] }}
                                    <span class="rpt-group-rule" aria-hidden="true"></span>
                                </h3>

                                @foreach ($group['items'] as $item)
                                    <div class="rpt-item">
                                        <span class="rpt-item-num" aria-hidden="true">{{ $item['number'] }}</span>

                                        <div class="rpt-item-body">
                                            {{-- The phase, if the judge named one —
                                                 the reader needs to know which
                                                 speech to reopen. The kind of
                                                 finding is already the heading
                                                 above it, so it is not repeated
                                                 on every line. --}}
                                            @if ($item['phase'])
                                                <div class="rpt-item-meta">
                                                    <span class="rpt-phase">In the {{ ucfirst($item['phase']) }}</span>
                                                </div>
                                            @endif

                                            @if ($item['quote'])
                                                <blockquote class="rpt-quote" style="{{ $fontStyle }}">{{ $item['quote'] }}</blockquote>
                                            @endif

                                            <p class="rpt-item-text" style="{{ $fontStyle }}">{{ $item['text'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ── 04 · Reasoning errors ───────────────────────────────── --}}
            @if (! empty($adj->fallacies))
                <section class="rpt-section" id="reasoning" aria-labelledby="rpt-reasoning-title">
                    <div class="rpt-section-head">
                        <h2 class="rpt-section-title" id="rpt-reasoning-title">
                            <span class="rpt-section-num" aria-hidden="true">04</span>
                            Reasoning errors
                        </h2>
                        <p class="rpt-section-note">
                            {{ count($adj->fallacies) }} {{ \Illuminate\Support\Str::plural('error', count($adj->fallacies)) }} flagged, with the phase each was spoken in.
                        </p>
                    </div>

                    <div class="rpt-section-body">
                        @foreach ($adj->fallacies as $i => $fallacy)
                            <div class="rpt-fallacy">
                                <div>
                                    <div class="rpt-fallacy-type">{{ $fallacy['type'] ?? 'Unspecified error' }}</div>
                                    @if (! empty($fallacy['phase']))
                                        <span class="rpt-fallacy-phase">{{ ucfirst($fallacy['phase']) }}</span>
                                    @endif
                                </div>
                                <p class="rpt-fallacy-text" style="{{ $fontStyle }}">{{ $fallacy['explanation'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ── 05 · The round ──────────────────────────────────────── --}}
            <section class="rpt-section" id="round" aria-labelledby="rpt-round-title">
                <div class="rpt-section-head">
                    <h2 class="rpt-section-title" id="rpt-round-title">
                        <span class="rpt-section-num" aria-hidden="true">05</span>
                        The round
                    </h2>
                    <p class="rpt-section-note">
                        @if ($delivery['available'])
                            {{ $delivery['total_words'] }} {{ \Illuminate\Support\Str::plural('word', $delivery['total_words']) }}
                            across {{ count($phases) }} {{ \Illuminate\Support\Str::plural('phase', count($phases)) }},
                            against {{ App\Support\ReportAnalysis::duration($delivery['total_budget']) }} of speaking time.
                        @else
                            Each phase of the round, in order.
                        @endif
                    </p>
                </div>

                <div class="rpt-section-body">
                    @foreach ($phases as $phase)
                        <div class="rpt-phase-row">
                            <div>
                                <div class="rpt-phase-name">{{ $phase['label'] }}</div>
                                <span class="rpt-phase-stats">
                                    {{ $phase['words'] }} {{ \Illuminate\Support\Str::plural('word', $phase['words']) }}
                                    @if ($phase['budget'] > 0)
                                        &middot; {{ $phase['budget'] }}s allowed
                                    @endif
                                </span>
                            </div>

                            {{-- The exchange, each speech labelled with the
                                 side that made it. The opening of each
                                 speech is shown here — enough to judge the
                                 framing. The whole speech is in section 06,
                                 so the two do not repeat each other. --}}
                            <div class="rpt-phase-body">
                                @foreach ($phase['turns'] as $turn)
                                    <div class="rpt-phase-turn{{ $turn['speaker'] === 'user' ? ' is-own' : '' }}">
                                        <span class="rpt-phase-who">{{ $turn['side'] }}</span>
                                        <p class="rpt-phase-text" style="{{ $fontStyle }}">{{ $turn['excerpt'] }}</p>
                                    </div>
                                @endforeach

                                {{-- What the time means for this phase. A
                                     percentage on its own is a number the
                                     reader has to interpret; here it is
                                     said in the terms of the phase. --}}
                                @if ($phase['note']['text'] !== '')
                                    <p class="rpt-phase-verdict rpt-phase-verdict--{{ $phase['note']['tone'] }}">
                                        {{ $phase['note']['text'] }}
                                    </p>
                                @endif
                            </div>

                            @if ($phase['budget'] > 0)
                                <div class="rpt-time">
                                    <span class="rpt-time-bar">
                                        <span class="rpt-time-fill {{ $phase['fill'] < 55 ? 'rpt-time-fill--short' : ($phase['fill'] > 105 ? 'rpt-time-fill--long' : '') }}"
                                              style="width: {{ min(100, $phase['fill']) }}%;"></span>
                                    </span>
                                    <span class="rpt-time-note {{ $phase['fill'] < 55 ? 'rpt-time-note--short' : ($phase['fill'] > 105 ? 'rpt-time-note--long' : '') }}">
                                        @if ($phase['fill'] < 55)
                                            {{ $phase['fill'] }}% used — {{ App\Support\ReportAnalysis::duration($phase['budget'] - $phase['seconds']) }} left
                                        @elseif ($phase['fill'] > 105)
                                            {{ $phase['fill'] }}% used — over time
                                        @else
                                            {{ $phase['fill'] }}% used — {{ App\Support\ReportAnalysis::duration($phase['seconds']) }} spoken
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            @endif

            {{-- ── 06 · The record ─────────────────────────────────────── --}}
            <section class="rpt-section" id="record" aria-labelledby="rpt-record-title">
                <div class="rpt-section-head">
                    <h2 class="rpt-section-title" id="rpt-record-title">
                        <span class="rpt-section-num" aria-hidden="true">06</span>
                        The record
                    </h2>
                    <p class="rpt-section-note">
                        Every speech of the round, in the order it was delivered.
                    </p>
                </div>

                <div class="rpt-section-body">
                    {{-- The control for the appendix. Reading the whole
                         round back is a deliberate act, so it has one. --}}
                    <div class="rpt-record-tools">
                        <button type="button" class="rpt-record-toggle" data-expand-all aria-expanded="false">
                            <span data-expand-label>Show the whole transcript</span>
                        </button>
                    </div>

                    {{-- The facts of the session. They sit with the record
                         because the record is what they describe. --}}
                    <div class="rpt-facts">
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Your side</span>
                            <span class="rpt-fact-value">{{ $debate->user_side }}</span>
                        </div>
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Opponent</span>
                            <span class="rpt-fact-value">{{ $debate->persona->name }}</span>
                        </div>
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Difficulty</span>
                            <span class="rpt-fact-value">{{ str_replace('_', ' ', $debate->difficulty) }}</span>
                        </div>
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Format</span>
                            <span class="rpt-fact-value">{{ ucfirst($debate->mode) }}</span>
                        </div>
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Language</span>
                            <span class="rpt-fact-value">{{ strtoupper($debate->language) }}</span>
                        </div>
                        <div class="rpt-fact">
                            <span class="rpt-fact-label">Debated</span>
                            <span class="rpt-fact-value">{{ $debate->created_at->format('d M Y') }}</span>
                        </div>
                    </div>

                    @foreach ($record as $turn)
                        {{-- The verbatim speech. It is the appendix of the
                             report: the words as they were delivered, so a
                             judge can check the mark against the round and
                             a debater can reread their own speech. It is
                             kept in a disclosure because most readers want
                             the analysis, not the transcript — but the
                             transcript is never more than one click away,
                             and the Export PDF button carries it too. --}}
                        <details class="rpt-turn {{ $turn['speaker'] === 'user' ? 'rpt-turn--user' : '' }}">
                            <summary class="rpt-turn-summary">
                                <span class="rpt-turn-who">
                                    <span class="rpt-turn-side">{{ $turn['side'] }}</span>
                                    <span class="rpt-turn-phase">{{ $turn['phase'] }}</span>
                                </span>
                                <span class="rpt-turn-meta">
                                    {{ $turn['words'] }} {{ \Illuminate\Support\Str::plural('word', $turn['words']) }}
                                    <span class="rpt-turn-toggle" aria-hidden="true"></span>
                                </span>
                            </summary>
                            <p class="rpt-turn-text" style="{{ $fontStyle }}">{{ $turn['transcript'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- ── The drill ────────────────────────────────────────────── --}}
            @if ($drill)
                <div class="rpt-drill">
                    <span class="rpt-drill-icon" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    </span>
                    <div class="rpt-drill-body">
                        <p class="rpt-drill-title">Practice drill</p>
                        <p class="rpt-drill-text" style="{{ $fontStyle }}">{{ $drill['text'] }}</p>
                    </div>
                </div>
            @endif

        </div>

        {{-- ── The contents ────────────────────────────────────────────── --}}
        <nav class="rpt-toc" aria-label="Report contents">
            <p class="rpt-toc-title">Contents</p>
            <ol class="rpt-toc-list">
                @foreach ($sections as $section)
                    <li>
                        <a class="rpt-toc-link" href="#{{ $section['id'] }}">
                            @if ($section['num'] !== '')
                                <span class="rpt-toc-num" aria-hidden="true">{{ $section['num'] }}</span>
                            @endif
                            <span>{{ $section['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>

    </div>

    {{-- ── Next action ───────────────────────────────────────────────── --}}
    <div class="rpt-footer">
        <a href="{{ route('setup') }}" class="surf-btn surf-btn-primary surf-btn-lg">
            Run Another Debate
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="{{ route('history.index') }}" class="surf-btn surf-btn-outline surf-btn-lg">Back to History</a>
    </div>

</div>

<script>
/**
 * Export the report as a PDF.
 *
 * The print stylesheet does the work: the browser's print dialog is
 * where a PDF is made, and the page arrives at it already laid out for
 * paper. The toolbar is hidden for the duration so the buttons do not
 * end up on the sheet.
 */
function exportPDF() {
    const toolbar = document.querySelector('.rpt-toolbar');

    if (toolbar) {
        toolbar.style.display = 'none';
    }

    // Every speech is opened before the page is sent to the printer.
    // A closed disclosure prints as a closed disclosure, and the
    // transcript is the appendix the reader asked for.
    const turns = [...document.querySelectorAll('.rpt-turn')];
    const wasOpen = turns.map((turn) => turn.open);

    turns.forEach((turn) => { turn.open = true; });

    window.print();

    // The print dialog is modal in every browser, so the toolbar can be
    // put back as soon as it returns.
    if (toolbar) {
        toolbar.style.display = '';
    }

    turns.forEach((turn, i) => { turn.open = wasOpen[i]; });
}

/**
 * Copy the plain-text transcript to the clipboard.
 *
 * The button says what happened either way — the clipboard is refused
 * outside a secure context, and a button that does nothing is a dead
 * end for the reader.
 */
async function copyTranscript(url, btn) {
    const label = btn.querySelector('[data-copy-label]');
    const original = label ? label.textContent : '';

    try {
        const res = await fetch(url, { headers: { 'Accept': 'text/plain' } });

        if (!res.ok) {
            throw new Error('The transcript could not be loaded.');
        }

        const text = await res.text();

        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            throw new Error('Clipboard is unavailable in this browser.');
        }

        await navigator.clipboard.writeText(text);

        if (label) label.textContent = 'Copied';
        btn.classList.add('is-copied');
    } catch (err) {
        console.error('Copy failed:', err);

        if (label) label.textContent = 'Copy failed';
        btn.classList.add('is-failed');
    }

    setTimeout(() => {
        if (label) label.textContent = original;
        btn.classList.remove('is-copied', 'is-failed');
    }, 2200);
}

/**
 * Open or close every speech in the record.
 *
 * The transcript is disclosed speech by speech so that the appendix
 * does not bury the analysis, but a reader who wants the whole round
 * should not have to click six times to get it.
 */
(function () {
    const button = document.querySelector('[data-expand-all]');

    if (!button) {
        return;
    }

    const label = button.querySelector('[data-expand-label]');
    const turns = [...document.querySelectorAll('.rpt-turn')];

    button.addEventListener('click', () => {
        const expand = button.getAttribute('aria-expanded') !== 'true';

        turns.forEach((turn) => {
            turn.open = expand;
        });

        button.setAttribute('aria-expanded', String(expand));

        if (label) {
            label.textContent = expand ? 'Hide the transcript' : 'Show the whole transcript';
        }
    });

    // The browser's own print — Ctrl+P, or the menu — does not go
    // through the toolbar button, so the speeches are opened here too.
    window.addEventListener('beforeprint', () => {
        turns.forEach((turn) => { turn.open = true; });
    });
})();
</script>

@endsection

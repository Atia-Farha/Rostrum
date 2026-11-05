@extends('layouts.app')

@section('title', 'Dashboard — Rostrum')

@section('content')

{{--
    The overview page.

    It answers three questions, in the order a practitioner asks them:

        1. how much have I done      — the metric strip
        2. how have I been doing      — the analytics band
        3. what did I do last         — the four most recent debates

    Nothing else is on the page. The rail already carries the one action
    the product exists for — starting a debate — so the page itself is a
    report and not a launcher. A resume band and a featured report used
    to sit here too, but each of them repeated a debate the record below
    already listed, and a dashboard that says the same thing twice in
    two shapes reads as clutter rather than as emphasis.
--}}
<div class="surf surf--wide">

    {{-- ── 1 · Header ─────────────────────────────────────────────────── --}}
    <div class="surf-head">
        <div class="surf-head-body">
            <span class="surf-eyebrow">Overview</span>
            <h1 class="surf-title">
                {{ $stats['total'] > 0 ? 'Welcome back' : 'Welcome' }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}
            </h1>
            <p class="surf-subtitle">
                @if ($stats['total'] > 0)
                    You have run {{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('debate', $stats['total']) }}
                    and earned {{ $stats['adjudicated'] }} {{ \Illuminate\Support\Str::plural('report', $stats['adjudicated']) }}.
                    @if ($stats['in_progress'] > 0)
                        {{ $stats['in_progress'] }} {{ \Illuminate\Support\Str::plural('debate', $stats['in_progress']) }} still in progress.
                    @endif
                @else
                    Pick a motion, choose an opponent, and speak your first round.
                @endif
            </p>
        </div>
    </div>

    {{-- ── 2 · The metric strip ───────────────────────────────────────── --}}
    {{-- Four tiles, one band. Each tile says exactly one thing: the
         number, and the fact that gives the number its meaning. --}}
    <div class="surf-stats">
        <div class="surf-stat">
            <span class="surf-stat-label">Debates</span>
            <span class="surf-stat-value">{{ $stats['total'] }}</span>
            <span class="surf-stat-note">
                @if ($stats['total'] > 0)
                    {{ $stats['tournament'] }} tournament &middot; {{ $stats['sparring'] }} sparring
                @else
                    No sessions yet
                @endif
            </span>
        </div>

        <div class="surf-stat">
            <span class="surf-stat-label">Reports</span>
            <span class="surf-stat-value">{{ $stats['adjudicated'] }}</span>
            <span class="surf-stat-note">
                @if ($stats['total'] > 0)
                    {{ $stats['tournament_reports'] }} tournament &middot; {{ $stats['sparring_reports'] }} sparring
                @else
                    No reports yet
                @endif
            </span>
        </div>

        <div class="surf-stat">
            <span class="surf-stat-label">Avg Score</span>
            <span class="surf-stat-value">
                {{ $stats['avg_score'] ?? '—' }}@if ($stats['avg_score'] !== null)<span class="surf-stat-denom">/100</span>@endif
            </span>
            <span class="surf-stat-note">
                @if ($stats['adjudicated'] > 0)
                    Across {{ $stats['adjudicated'] }} {{ \Illuminate\Support\Str::plural('report', $stats['adjudicated']) }}
                @else
                    No scores yet
                @endif
            </span>
        </div>

        <div class="surf-stat">
            <span class="surf-stat-label">Best Score</span>
            <span class="surf-stat-value">
                {{ $stats['best_score'] ?? '—' }}@if ($stats['best_score'] !== null)<span class="surf-stat-denom">/100</span>@endif
            </span>
            <span class="surf-stat-note">
                @if ($stats['adjudicated'] > 0)
                    Your highest mark
                @else
                    No scores yet
                @endif
            </span>
        </div>
    </div>

    {{-- ── 3 · Analytics ──────────────────────────────────────────────── --}}
    {{-- Three readings of the same practice: how the score has moved
         over time, where the marks are won, and what has been
         attempted. --}}
    <div class="surf-charts">

        {{-- 3a · The score trend. --}}
        <div class="surf-panel surf-chart-card surf-chart-card--trend">
            <div class="surf-panel-head">
                <h2 class="surf-panel-title">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/></svg>
                    Score Trend
                </h2>
                <span class="surf-panel-meta">
                    @if ($stats['adjudicated'] > 0)
                        Last {{ $stats['adjudicated'] }} {{ \Illuminate\Support\Str::plural('report', $stats['adjudicated']) }}
                    @else
                        No scores yet
                    @endif
                </span>
            </div>

            <div class="surf-panel-body">
                @php
                    $plot = new \App\Support\ScoreTrend($trend);
                    $gridlines = $plot->gridlines();
                    $xs = $plot->xs();
                    $ys = $plot->ys();
                    $scores = $plot->scores();
                @endphp

                {{-- The scale sits outside the plot and the readings are
                     real text laid over it, so the chart never depends
                     on colour alone to be read and the numbers stay the
                     same size whatever the chart is scaled to. --}}
                <div class="surf-plot">
                    <div class="surf-plot-scale" aria-hidden="true">
                        @foreach (array_reverse($gridlines) as $line)
                            <span class="surf-scale-value" style="top: {{ \App\Support\ScoreTrend::topPercentFor($line) }}%;">{{ $line }}</span>
                        @endforeach
                    </div>

                    <div class="surf-plot-canvas">
                        <svg class="surf-plot-svg" viewBox="0 0 640 200" preserveAspectRatio="none"
                             role="img"
                             aria-label="@if (count($trend) === 0) No scores to plot yet. @else Score over the last {{ count($trend) }} {{ \Illuminate\Support\Str::plural('report', count($trend)) }}, from {{ $plot->firstLabel() }} to {{ $plot->lastLabel() }}. Scores: {{ implode(', ', $scores) }}. @endif">
                            <defs>
                                <linearGradient id="surf-trend-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--color-accent)" stop-opacity="0.28"></stop>
                                    <stop offset="100%" stop-color="var(--color-accent)" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>

                            @foreach ($gridlines as $line)
                                <line class="surf-plot-grid" x1="0" y1="{{ $plot->yFor($line) }}" x2="640" y2="{{ $plot->yFor($line) }}"></line>
                            @endforeach

                            @if (count($trend) > 0)
                                <path class="surf-plot-area" d="{{ $plot->areaPath() }}"></path>
                                <path class="surf-plot-line" d="{{ $plot->linePath() }}"></path>
                            @endif
                        </svg>

                        @if (count($trend) > 0)
                            @foreach ($scores as $index => $score)
                                @php
                                    // The reading sits above its point, but a
                                    // point near the top of the plot has no
                                    // room above it, so it drops below
                                    // instead of being clipped.
                                    $above = $ys[$index] > 14;
                                    $left = ($xs[$index] / \App\Support\ScoreTrend::WIDTH) * 100;
                                    $top = ($ys[$index] / \App\Support\ScoreTrend::HEIGHT) * 100;
                                @endphp
                                <span class="surf-plot-point" aria-hidden="true"
                                      style="left: {{ $left }}%; top: {{ $top }}%;"></span>
                                <span class="surf-plot-value" aria-hidden="true"
                                      style="left: {{ $left }}%; top: calc({{ $top }}% {{ $above ? '-' : '+' }} 13px);">{{ $score }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>

                @if (count($trend) > 0)
                    <div class="surf-plot-axis">
                        <span>{{ $plot->firstLabel() }}</span>
                        <span>{{ $plot->lastLabel() }}</span>
                    </div>
                @else
                    <p class="surf-prose surf-chart-note">
                        Finish a debate and the adjudicator's mark is plotted here.
                    </p>
                @endif
            </div>
        </div>

        {{-- 3b · Where the marks are won. --}}
        <div class="surf-panel surf-chart-card surf-chart-card--rubric">
            <div class="surf-panel-head">
                <h2 class="surf-panel-title">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-6"/></svg>
                    Rubric Averages
                </h2>
                <span class="surf-panel-meta">
                    @if ($stats['adjudicated'] > 0)
                        Out of {{ $criteria[0]['max'] }} &middot; {{ $criteria[1]['max'] }} &middot; {{ $criteria[2]['max'] }}
                    @else
                        No scores yet
                    @endif
                </span>
            </div>

            <div class="surf-panel-body @if (! $breakdown) surf-panel-body--empty @endif">
                @if ($breakdown)
                    <div class="surf-bars">
                        @foreach ($criteria as $criterion)
                            @php
                                $value = $breakdown[$criterion['key']];
                                $share = ($value / $criterion['max']) * 100;
                                $colour = $criterion['key'] === 'matter' ? 'accent' : 'accent-2';
                            @endphp
                            <div class="surf-bar-row">
                                <span class="surf-bar-label">{{ $criterion['label'] }}</span>
                                <span class="surf-bar-track">
                                    <span class="surf-bar-fill surf-bar-fill--{{ $colour }}" style="width: {{ $share }}%;"></span>
                                </span>
                                <span class="surf-bar-count">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="surf-legend">
                        <span class="surf-legend-item">
                            <span class="surf-legend-swatch surf-legend-swatch--accent" aria-hidden="true"></span>
                            Matter &middot; out of {{ $criteria[0]['max'] }}
                        </span>
                        <span class="surf-legend-item">
                            <span class="surf-legend-swatch surf-legend-swatch--accent-2" aria-hidden="true"></span>
                            Manner &amp; Method &middot; out of {{ $criteria[1]['max'] }}
                        </span>
                    </div>
                @else
                    <p class="surf-prose surf-chart-note">
                        Finish a debate and the adjudicator's marks are broken down here.
                    </p>
                @endif
            </div>
        </div>

        {{-- 3c · What has been attempted. --}}
        <div class="surf-panel surf-chart-card surf-chart-card--difficulty">
            <div class="surf-panel-head">
                <h2 class="surf-panel-title">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                    Reports by Difficulty
                </h2>
                <span class="surf-panel-meta">
                    {{ $stats['adjudicated'] }} {{ \Illuminate\Support\Str::plural('report', $stats['adjudicated']) }}
                </span>
            </div>

            <div class="surf-panel-body @if ($stats['adjudicated'] === 0) surf-panel-body--empty @endif">
                @if ($stats['adjudicated'] > 0)
                    @php $maxDifficulty = max(1, collect($byDifficulty)->max('count')); @endphp

                    <div class="surf-bars">
                        @foreach ($byDifficulty as $band)
                            @php $share = ($band['count'] / $maxDifficulty) * 100; @endphp
                            <div class="surf-bar-row {{ $band['count'] === 0 ? 'surf-bar-row--empty' : '' }}">
                                <span class="surf-bar-label">{{ $band['label'] }}</span>
                                <span class="surf-bar-track">
                                    <span class="surf-bar-fill" style="width: {{ $band['count'] > 0 ? $share : 0 }}%;"></span>
                                </span>
                                <span class="surf-bar-count">{{ $band['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="surf-prose surf-chart-note">
                        Once a debate is scored, the difficulty you take on shows here.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── 4 · The four most recent debates ───────────────────────────── --}}
    {{-- The last of the three questions. Four is enough to show a shape
         without becoming a second copy of the history page. --}}
    <div class="surf-panel">
        <div class="surf-panel-head">
            <h2 class="surf-panel-title">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                Recent Debates
            </h2>
            @if ($stats['total'] > 0)
                <a href="{{ route('history.index') }}" class="surf-btn surf-btn-outline surf-btn-sm">
                    @if ($stats['total'] > count($recentDebates))
                        View all {{ $stats['total'] }}
                    @else
                        View history
                    @endif
                </a>
            @endif
        </div>

        @if ($recentDebates->isEmpty())
            <div class="surf-empty">
                <div class="surf-empty-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" x2="8" y1="13" y2="13"/>
                        <line x1="16" x2="8" y1="17" y2="17"/>
                    </svg>
                </div>
                <h3 class="surf-empty-title">No debates yet</h3>
                <p class="surf-empty-text">
                    Choose a motion, pick your opponent, and speak your first round.
                </p>
                <div class="surf-empty-action">
                    <a href="{{ route('setup') }}" class="surf-btn surf-btn-primary">Start Your First Debate</a>
                </div>
            </div>
        @else
            <div class="surf-table-wrap">
                <table class="surf-table surf-table--ledger">
                    <thead>
                        <tr>
                            <th scope="col" class="col-primary">Motion</th>
                            <th scope="col" class="col-person">Opponent</th>
                            <th scope="col" class="col-date">Date</th>
                            <th scope="col" class="is-numeric col-score">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentDebates as $debate)
                            @php
                                $motionText = $debate->motion->textFor($debate->language);
                                $adj = $debate->adjudication;
                            @endphp
                            <tr>
                                <td data-label="" class="col-primary">
                                    <a href="{{ $adj ? route('debates.feedback', $debate->id) : route('debates.show', $debate->id) }}"
                                       class="surf-cell-primary surf-cell-clamp">{{ $motionText }}</a>
                                    {{-- The format travels with the motion
                                         rather than in a column of its own:
                                         a fifth column squeezed the
                                         record's own name into a
                                         two-word gutter. --}}
                                    <span class="surf-cell-sub">{{ ucfirst($debate->mode) }}</span>
                                </td>
                                <td data-label="Opponent" class="col-person">{{ $debate->persona->name }}</td>
                                <td data-label="Date" class="col-date">{{ $debate->created_at->format('d M Y') }}</td>
                                <td data-label="Score" class="is-numeric col-score">
                                    @if ($adj)
                                        <span class="surf-score">
                                            <span class="surf-score-value">{{ $adj->total_score }}</span>
                                            <span class="surf-score-denom">/100</span>
                                        </span>
                                    @else
                                        {{-- An unscored session has no number to
                                             show, so the cell says what the
                                             session is instead. --}}
                                        <span class="surf-pill surf-pill--live">In progress</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@endsection

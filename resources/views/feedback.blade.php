@extends('layouts.app')

@section('title', 'Adjudication Report — Rostrum')

@section('content')

@php
    $adj        = $debate->adjudication;
    $motionText = $debate->motion->textFor($debate->language);
    $isBn       = $debate->language === 'bn';
    $fontStyle  = $isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';

    $totalScore = $adj?->total_score ?? 0;
    $grade = match(true) {
        $totalScore >= 85 => ['label' => 'Distinction', 'color' => '#22c55e'],
        $totalScore >= 70 => ['label' => 'Score',       'color' => '#3b82f6'],
        $totalScore >= 55 => ['label' => 'Pass',        'color' => '#f59e0b'],
        default           => ['label' => 'Below Pass',  'color' => '#ef4444'],
    };
@endphp

<style>
    /* ── Report Shell ── */
    .rpt-page {
        max-width: 860px;
        margin: 0 auto;
        padding: 2.5rem 1.25rem 6rem;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* ── Document Cover ── */
    .rpt-cover {
        background: #0a0d16;
        border: 1px solid #1e2535;
        border-top: 3px solid #4f5edd;
        border-radius: var(--radius-lg, 4px);
        padding: clamp(1.5rem, 4vw, 2.5rem);
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
    }

    .rpt-cover-eyebrow {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .rpt-document-tag {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #818cf8;
        border: 1px solid #2d3566;
        padding: 0.3rem 0.8rem;
        border-radius: 3px;
        background: rgba(79, 94, 221, 0.08);
    }

    .rpt-meta-row {
        font-size: 0.75rem;
        color: #7b91b3;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .rpt-session-link {
        color: #818cf8;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
        transition: color 0.15s ease, opacity 0.15s ease;
    }

    .rpt-session-link:hover {
        color: #a5b4fc;
        text-decoration: underline;
        opacity: 1;
    }

    .rpt-motion {
        font-size: clamp(1.15rem, 3vw, 1.5rem);
        font-weight: 700;
        color: #f0f4ff;
        line-height: 1.5;
        margin: 0 0 1.5rem;
        letter-spacing: -0.01em;
    }

    .rpt-motion::before {
        content: '"';
        color: #4f5edd;
        font-size: 1.6rem;
        line-height: 0;
        vertical-align: -0.2em;
        margin-right: 2px;
    }

    .rpt-motion::after {
        content: '"';
        color: #4f5edd;
        font-size: 1.6rem;
        line-height: 0;
        vertical-align: -0.2em;
        margin-left: 2px;
    }

    .rpt-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.75rem;
    }

    .rpt-field {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        padding: 0.85rem 1rem;
    }

    .rpt-field-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #7b91b3;
        margin-bottom: 0.3rem;
    }

    .rpt-field-value {
        font-size: 0.88rem;
        font-weight: 600;
        color: #f0f4ff;
    }

    /* ── Section Container ── */
    .rpt-section {
        background: #0a0d16;
        border: 1px solid #1e2535;
        border-radius: var(--radius-lg, 4px);
        padding: clamp(1.25rem, 3vw, 2rem);
        margin-bottom: 1.25rem;
        transition: border-color 0.25s ease;
    }

    .rpt-section:hover {
        border-color: rgba(99, 102, 241, 0.3);
    }

    .rpt-section-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.9rem;
        border-bottom: 1px solid #111827;
    }

    .rpt-section-number {
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        color: #818cf8;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.25);
        padding: 0.2rem 0.55rem;
        border-radius: 3px;
        min-width: 28px;
        text-align: center;
    }

    .rpt-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #c8d5f0;
    }

    /* ── Verdict ── */
    .rpt-verdict-wrap {
        display: flex;
        align-items: flex-start;
        gap: 1.25rem;
    }

    .rpt-verdict-bar {
        width: 3px;
        min-height: 100%;
        border-radius: 2px;
        background: #4f5edd;
        flex-shrink: 0;
        align-self: stretch;
    }

    .rpt-verdict-text {
        font-size: 1.05rem;
        font-weight: 500;
        color: #d4deee;
        line-height: 1.65;
        flex: 1;
    }

    /* ── Score Table ── */
    .rpt-score-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .rpt-score-cell {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        padding: 1.1rem 1.25rem;
    }

    .rpt-score-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #7b91b3;
        margin-bottom: 0.3rem;
    }

    .rpt-score-description {
        font-size: 0.75rem;
        color: #7b91b3;
        margin-bottom: 0.75rem;
        line-height: 1.45;
    }

    .rpt-score-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .rpt-score-bar-bg {
        flex: 1;
        height: 6px;
        background: #1e2535;
        border-radius: 3px;
        overflow: hidden;
    }

    .rpt-score-bar-fill {
        height: 100%;
        border-radius: 3px;
        background: #4f5edd;
    }

    .rpt-score-bar-fill.matter { background: #38bdf8; }
    .rpt-score-bar-fill.manner { background: #c084fc; }
    .rpt-score-bar-fill.method { background: #34d399; }

    .rpt-score-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: #f0f4ff;
        white-space: nowrap;
    }

    /* ── Total Score ── */
    .rpt-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        padding: 1.25rem 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .rpt-total-label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .rpt-total-right {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .rpt-total-score {
        font-size: 2.25rem;
        font-weight: 800;
        color: #f0f4ff;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .rpt-total-denom {
        font-size: 0.9rem;
        color: #7b91b3;
        font-weight: 600;
    }

    .rpt-grade-badge {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.35rem 0.85rem;
        border-radius: 3px;
    }

    /* ── Fallacies ── */
    .rpt-fallacy-item {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 1.25rem;
        padding: 1.1rem 0;
        border-bottom: 1px solid #111827;
        align-items: start;
    }

    .rpt-fallacy-item:last-child { border-bottom: none; }

    .rpt-fallacy-index {
        font-size: 0.68rem;
        font-weight: 800;
        color: #fbbf24;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.25);
        padding: 0.25rem 0.55rem;
        border-radius: 3px;
        letter-spacing: 0.06em;
        white-space: nowrap;
        margin-top: 2px;
    }

    .rpt-fallacy-type {
        font-size: 0.88rem;
        font-weight: 700;
        color: #fbbf24;
        margin-bottom: 0.3rem;
    }

    .rpt-fallacy-explanation {
        font-size: 0.88rem;
        color: #94a3b8;
        line-height: 1.65;
    }

    /* ── Feedback Bullets ── */
    .rpt-finding {
        display: grid;
        grid-template-columns: 22px 1fr;
        gap: 0.75rem;
        padding: 1rem 0;
        border-bottom: 1px solid #111827;
        align-items: start;
    }

    .rpt-finding:last-child { border-bottom: none; }

    .rpt-finding-num {
        font-size: 0.68rem;
        font-weight: 800;
        color: #818cf8;
        padding-top: 4px;
        font-variant-numeric: tabular-nums;
    }

    .rpt-finding-text {
        font-size: 0.925rem;
        color: #c8d5f0;
        line-height: 1.7;
    }

    .rpt-tag {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.2rem 0.55rem;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 0.4rem;
        position: relative;
        top: -1px;
    }

    .rpt-tag-strength  { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.25); }
    .rpt-tag-issue     { background: rgba(239,68,68,0.10);  color: #f87171; border: 1px solid rgba(239,68,68,0.22); }
    .rpt-tag-tip       { background: rgba(56,189,248,0.10); color: #38bdf8; border: 1px solid rgba(56,189,248,0.22); }
    .rpt-tag-note      { background: rgba(245,158,11,0.10); color: #fbbf24; border: 1px solid rgba(245,158,11,0.22); }

    /* ── No Fallacies ── */
    .rpt-clean-slate {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        background: rgba(16, 185, 129, 0.05);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 4px;
        font-size: 0.88rem;
        color: #6ee7b7;
    }

    /* ── Nav Bar ── */
    .rpt-navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .rpt-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.825rem;
        font-weight: 600;
        padding: 0.55rem 1.1rem;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
        border: 1px solid transparent;
    }

    .rpt-btn-ghost {
        background: #06080f;
        border-color: #1e2535;
        color: #94a3b8;
    }

    .rpt-btn-ghost:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f0f4ff;
    }

    .rpt-btn-outline {
        background: transparent;
        border-color: rgba(102, 117, 245, 0.3);
        color: #818cf8;
    }

    .rpt-btn-outline:hover {
        background: rgba(102, 117, 245, 0.12);
        border-color: rgba(102, 117, 245, 0.5);
        color: #f0f4ff;
    }

    /* ── CTA ── */
    .rpt-cta-row {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 2.5rem;
        gap: 0.75rem;
    }

    .rpt-btn-primary {
        background: #4f5edd;
        border: 1px solid #3d4bc9;
        color: #fff;
        font-size: 0.925rem;
        font-weight: 600;
        padding: 0.75rem 2.25rem;
        border-radius: var(--landing-radius, 3px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .rpt-btn-primary:hover { 
        background: #3d4bc9; 
        color: #fff; 
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    }

    @media (max-width: 640px) {
        .rpt-page { padding-inline: 1rem; }
        .rpt-navbar { flex-direction: column; align-items: stretch; }
        .rpt-navbar > div { width: 100%; flex-direction: column; }
        .rpt-btn { width: 100%; justify-content: center; }
        .rpt-total-row { flex-direction: column; align-items: flex-start; }
        .rpt-total-right { width: 100%; justify-content: space-between; }
        .rpt-btn-primary { width: 100%; justify-content: center; }
    }

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
        .nav, .skip-link, .rpt-navbar, .rpt-cta-row { display: none !important; }
        #main-content { padding-top: 0 !important; }
        .rpt-page { padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
        .rpt-cover, .rpt-section { 
            background: #ffffff !important; 
            border: 1px solid #cbd5e1 !important; 
            color: #0f172a !important; 
            box-shadow: none !important; 
            break-inside: auto !important;
            page-break-inside: auto !important;
            margin-bottom: 1rem !important;
            padding: 1.25rem !important;
        }
        .rpt-finding, .rpt-fallacy-item, .rpt-score-grid, .rpt-total-row {
            break-inside: auto !important;
            page-break-inside: auto !important;
        }
        .rpt-motion, .rpt-section-title, .rpt-field-value, .rpt-total-score, .rpt-finding-text, .rpt-verdict-text { color: #0f172a !important; }
        .rpt-field, .rpt-score-cell, .rpt-total-row { background: #f8fafc !important; border-color: #cbd5e1 !important; }
        .rpt-field-label, .rpt-score-label, .rpt-total-label, .rpt-meta-row { color: #475569 !important; }
    }
</style>

<div class="rpt-page">

    {{-- Navigation Bar --}}
    <div class="rpt-navbar">
        <a href="{{ route('debates.show', $debate->id) }}" class="rpt-btn rpt-btn-ghost">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Arena
        </a>
        <div style="display:flex; gap:0.5rem;">
            <button type="button" class="rpt-btn rpt-btn-ghost" onclick="copyTranscript('{{ route('debates.transcript', $debate->id) }}', this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                Copy Transcript
            </button>
            <button type="button" class="rpt-btn rpt-btn-outline" id="btn-download-pdf" onclick="exportPDF()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
                Export PDF
            </button>
        </div>
    </div>

    {{-- Document Cover --}}
    <div class="rpt-cover">
        <div class="rpt-cover-eyebrow">
            <span class="rpt-document-tag">Rostrum — Official Adjudication Report</span>
            <div class="rpt-meta-row">
                @if($adj)
                    @if(empty($adj->fallacies))
                        <span style="font-size:0.7rem; font-weight:700; color:#34d399; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); padding:0.2rem 0.6rem; border-radius:3px;">
                            ✓ 0 Fallacies Detected
                        </span>
                    @else
                        <span style="font-size:0.7rem; font-weight:700; color:#fbbf24; background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.25); padding:0.2rem 0.6rem; border-radius:3px;">
                            ⚠ {{ count($adj->fallacies) }} Fallacies Flagged
                        </span>
                    @endif
                @endif
                <a href="{{ route('debates.show', $debate->id) }}" class="rpt-session-link" title="View live debate arena session">
                    View Debate Session
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
                <span>{{ $debate->updated_at->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <p class="rpt-motion" style="{{ $fontStyle }}">{{ $motionText }}</p>

        <div class="rpt-fields">
            <div class="rpt-field">
                <div class="rpt-field-label">Debater's Side</div>
                <div class="rpt-field-value" style="text-transform:capitalize;">{{ $debate->user_side }}</div>
            </div>
            <div class="rpt-field">
                <div class="rpt-field-label">AI Opponent</div>
                <div class="rpt-field-value">{{ $debate->persona->name }}</div>
            </div>
            <div class="rpt-field">
                <div class="rpt-field-label">Difficulty</div>
                <div class="rpt-field-value" style="text-transform:capitalize;">{{ str_replace('_', ' ', $debate->difficulty) }}</div>
            </div>
            <div class="rpt-field">
                <div class="rpt-field-label">Language</div>
                <div class="rpt-field-value" style="text-transform:uppercase;">{{ $debate->language }}</div>
            </div>
        </div>
    </div>

    @if($adj)

    {{-- Section 1: Adjudicator's Verdict --}}
    <div class="rpt-section">
        <div class="rpt-section-head">
            <span class="rpt-section-number">01</span>
            <span class="rpt-section-title">Adjudicator's Verdict</span>
        </div>
        <div class="rpt-verdict-wrap">
            <div class="rpt-verdict-bar"></div>
            <p class="rpt-verdict-text" style="{{ $fontStyle }}">{{ $adj->verdict }}</p>
        </div>
    </div>

    {{-- Section 2: Score Breakdown --}}
    <div class="rpt-section">
        <div class="rpt-section-head">
            <span class="rpt-section-number">02</span>
            <span class="rpt-section-title">Performance Scores</span>
        </div>

        <div class="rpt-score-grid">
            <div class="rpt-score-cell">
                <div class="rpt-score-label">Matter</div>
                <div class="rpt-score-description">Content, arguments &amp; evidence</div>
                <div class="rpt-score-line">
                    <div class="rpt-score-bar-bg">
                        <div class="rpt-score-bar-fill matter" style="width:{{ ($adj->matter_score / 40) * 100 }}%;"></div>
                    </div>
                    <span class="rpt-score-value">{{ $adj->matter_score }}<span style="color:#7b91b3;font-weight:500;">/40</span></span>
                </div>
            </div>
            <div class="rpt-score-cell">
                <div class="rpt-score-label">Manner</div>
                <div class="rpt-score-description">Style, rhetoric &amp; persuasion</div>
                <div class="rpt-score-line">
                    <div class="rpt-score-bar-bg">
                        <div class="rpt-score-bar-fill manner" style="width:{{ ($adj->manner_score / 30) * 100 }}%;"></div>
                    </div>
                    <span class="rpt-score-value">{{ $adj->manner_score }}<span style="color:#7b91b3;font-weight:500;">/30</span></span>
                </div>
            </div>
            <div class="rpt-score-cell">
                <div class="rpt-score-label">Method</div>
                <div class="rpt-score-description">Structure, strategy &amp; timing</div>
                <div class="rpt-score-line">
                    <div class="rpt-score-bar-bg">
                        <div class="rpt-score-bar-fill method" style="width:{{ ($adj->method_score / 30) * 100 }}%;"></div>
                    </div>
                    <span class="rpt-score-value">{{ $adj->method_score }}<span style="color:#7b91b3;font-weight:500;">/30</span></span>
                </div>
            </div>
        </div>

        <div class="rpt-total-row">
            <span class="rpt-total-label">Overall Performance Score</span>
            <div class="rpt-total-right">
                <span class="rpt-grade-badge" style="color:{{ $grade['color'] }}; background:{{ $grade['color'] }}18; border:1px solid {{ $grade['color'] }}33;">
                    {{ $grade['label'] }}
                </span>
                <div>
                    <span class="rpt-total-score">{{ $adj->total_score }}</span>
                    <span class="rpt-total-denom"> / 100</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3: Logical Fallacies --}}
    <div class="rpt-section">
        <div class="rpt-section-head">
            <span class="rpt-section-number">03</span>
            <span class="rpt-section-title">Logical Fallacies &amp; Reasoning Errors</span>
        </div>

        @if(empty($adj->fallacies))
        <div class="rpt-clean-slate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            No formal logical fallacies were identified in this debate. Sound structural reasoning maintained throughout.
        </div>
        @else
        <div>
            @foreach($adj->fallacies as $i => $fallacy)
            <div class="rpt-fallacy-item">
                <span class="rpt-fallacy-index">{{ strtoupper($fallacy['phase'] ?? 'F' . ($i + 1)) }}</span>
                <div>
                    <div class="rpt-fallacy-type">{{ $fallacy['type'] ?? 'Unspecified Fallacy' }}</div>
                    <div class="rpt-fallacy-explanation" style="{{ $fontStyle }}">{{ $fallacy['explanation'] ?? '' }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Section 4: Adjudicator Findings --}}
    <div class="rpt-section">
        <div class="rpt-section-head">
            <span class="rpt-section-number">04</span>
            <span class="rpt-section-title">Adjudicator Findings &amp; Recommendations</span>
        </div>

        <div>
            @foreach($adj->feedback_bullets as $i => $bullet)
            @php
                $tagMap = [
                    'STRENGTH' => 'strength',
                    'ISSUE'    => 'issue',
                    'TIP'      => 'tip',
                    'NOTE'     => 'note',
                    'WARNING'  => 'issue',
                    'ADVICE'   => 'tip',
                    'GOOD'     => 'strength',
                    'WEAK'     => 'issue',
                ];
                $detectedTag   = null;
                $detectedClass = null;
                $cleanBullet   = $bullet;

                if (preg_match('/^\[([A-Z]+)\]\s*/u', $bullet, $m)) {
                    $key = strtoupper($m[1]);
                    $detectedTag   = $m[1];
                    $detectedClass = $tagMap[$key] ?? 'note';
                    $cleanBullet   = ltrim(substr($bullet, strlen($m[0])));
                }
                // Strip "You said \"[No speech provided]\" — " or variations if user provided no speech in turn
                $cleanBullet = preg_replace('/^You said\s*["\']\[No speech provided\]["\']\s*[\x{2014}\x{2013}\-:]\s*/u', '', $cleanBullet);
                $cleanBullet = preg_replace('/^You said\s*\[No speech provided\]\s*[\x{2014}\x{2013}\-:]\s*/u', '', $cleanBullet);
                $cleanBullet = mb_strtoupper(mb_substr($cleanBullet, 0, 1)) . mb_substr($cleanBullet, 1);
            @endphp
            <div class="rpt-finding">
                <span class="rpt-finding-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                <div class="rpt-finding-text" style="{{ $fontStyle }}">
                    @if($detectedTag)
                        <span class="rpt-tag rpt-tag-{{ $detectedClass }}">{{ $detectedTag }}</span>
                    @endif
                    {{ $cleanBullet }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @endif

    {{-- Footer Actions --}}
    <div class="rpt-cta-row">
        <a href="{{ route('setup') }}" class="rpt-btn-primary">
            Start New Debate
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
    </div>

</div>

<script>
function exportPDF() {
    // Hide buttons during capture
    const nav = document.querySelector('.rpt-navbar');
    const cta = document.querySelector('.rpt-cta-row');
    if (nav) nav.style.display = 'none';
    if (cta) cta.style.display = 'none';

    // Use window.print() which allows saving as PDF directly from browser print dialog with @media print CSS
    window.print();

    // Restore buttons after dialog triggers
    setTimeout(() => {
        if (nav) nav.style.display = '';
        if (cta) cta.style.display = '';
    }, 1000);
}

async function copyTranscript(url, btn) {
    try {
        const res  = await fetch(url);
        const text = await res.text();
        await navigator.clipboard.writeText(text);
        const orig = btn.innerHTML;
        btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copied`;
        setTimeout(() => btn.innerHTML = orig, 2200);
    } catch (err) {
        console.error('Copy failed:', err);
    }
}
</script>

@endsection

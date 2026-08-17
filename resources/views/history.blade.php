@extends('layouts.app')

@section('title', 'Session Records — Rostrum')

@section('content')

<style>
    /* ── Page wrapper ── */
    .hr-page {
        box-sizing: border-box;
        width: 100%;
        max-width: 860px;
        margin: 0 auto;
        padding: 2.5rem 1.5rem 5rem;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* ── Page header ── */
    .hr-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid #111827;
    }

    .hr-title {
        font-size: clamp(1.3rem, 3vw, 1.9rem);
        font-weight: 700;
        color: #f0f4ff;
        letter-spacing: -0.025em;
        margin: 0;
    }

    .hr-new-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 0.6rem 1.25rem;
        border-radius: 4px;
        background: #4f5edd;
        border: 1px solid #3d4bc9;
        color: #fff;
        text-decoration: none;
        transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .hr-new-btn:hover {
        background: #3d4bc9;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99,102,241,0.4);
    }

    /* ── Toolbar ── */
    .hr-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .hr-tabs {
        display: inline-flex;
        gap: 0.25rem;
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 6px;
        padding: 0.25rem;
        flex-shrink: 0;
    }

    .hr-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #7b91b3;
        padding: 0.45rem 0.9rem;
        border-radius: 4px;
        border: none;
        background: transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .hr-tab:hover { color: #f0f4ff; }
    .hr-tab.active { background: #4f5edd; color: #fff; }

    .hr-tab-count {
        font-size: 0.62rem;
        font-weight: 800;
        padding: 0.1rem 0.4rem;
        border-radius: 8px;
        background: rgba(255,255,255,0.08);
        color: #7b91b3;
        font-variant-numeric: tabular-nums;
    }

    .hr-tab.active .hr-tab-count {
        background: rgba(255,255,255,0.22);
        color: #fff;
    }

    .hr-search {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 6px;
        padding: 0.48rem 0.85rem;
        flex: 1;
        min-width: 0;
        max-width: 320px;
        transition: border-color 0.15s ease;
    }

    .hr-search:focus-within {
        border-color: #6675f5;
        box-shadow: 0 0 10px rgba(102,117,245,0.15);
    }

    .hr-search svg { flex-shrink: 0; color: #7b91b3; }

    .hr-search input {
        flex: 1;
        min-width: 0;
        background: transparent;
        border: none;
        outline: none;
        color: #f0f4ff;
        font-size: 0.83rem;
        font-family: inherit;
    }

    .hr-search input::-webkit-search-decoration,
    .hr-search input::-webkit-search-cancel-button,
    .hr-search input::-webkit-search-results-button,
    .hr-search input::-webkit-search-results-decoration {
        -webkit-appearance: none;
        appearance: none;
        display: none;
    }

    .hr-search input::placeholder { color: #687b99; }

    .hr-status-select {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 6px;
        color: #f0f4ff;
        font-size: 0.8rem;
        font-weight: 500;
        font-family: inherit;
        padding: 0.48rem 0.75rem;
        outline: none;
        cursor: pointer;
        transition: border-color 0.15s ease;
    }

    .hr-status-select:focus {
        border-color: #6675f5;
        box-shadow: 0 0 10px rgba(102,117,245,0.15);
    }

    .hr-search-clear {
        border: none;
        background: none;
        color: #5c7090;
        font-size: 1.1rem;
        line-height: 1;
        cursor: pointer;
        padding: 0.1rem 0.2rem;
        border-radius: 3px;
        flex-shrink: 0;
    }

    .hr-search-clear:hover { color: #fca5a5; }

    /* ── Card list ── */
    .hr-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    /* ── Card ── */
    .hr-card {
        box-sizing: border-box;
        width: 100%;
        background: #0a0d16;
        border: 1px solid #1e2535;
        border-radius: 8px;
        padding: 1.2rem 1.4rem;
        display: flex;
        gap: 1rem;
        align-items: stretch;
        transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden; /* prevent ANY child overflow */
    }

    .hr-card::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 3px;
        border-radius: 8px 0 0 8px;
        background: #2d3566;
    }

    .hr-card.is-complete::before { background: #4f5edd; }
    .hr-card.is-pending::before  { background: #d97706; }

    .hr-card:hover {
        border-color: #252e4a;
        background: #0d1020;
        box-shadow: 0 4px 24px rgba(0,0,0,0.35);
    }

    /* ── Card body (left, takes remaining space) ── */
    .hr-card-body {
        flex: 1 1 0%;   /* grow, shrink, basis 0 */
        min-width: 0;   /* critical: lets flex child shrink below content size */
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .hr-card-motion {
        font-size: 0.93rem;
        font-weight: 600;
        color: #f0f4ff;
        line-height: 1.55;
        margin: 0 0 0.55rem;
        /* Wrap long words; no overflow */
        overflow-wrap: break-word;
        word-break: break-word;
        hyphens: auto;
        /* Clamp to 3 lines max */
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .hr-card-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.3rem 0.5rem;
        margin-bottom: 0.55rem;
        min-width: 0;
    }

    .hr-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.69rem;
        font-weight: 600;
        color: #7b91b3;
        white-space: nowrap;
    }

    .hr-chip-dot {
        width: 2px; height: 2px;
        border-radius: 50%;
        background: #2c3a50;
        flex-shrink: 0;
        display: inline-block;
    }

    .hr-chip-tag {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 0.14rem 0.48rem;
        border-radius: 3px;
        white-space: nowrap;
    }

    .hr-chip-tag.side {
        color: #818cf8;
        background: rgba(99,102,241,0.1);
        border: 1px solid rgba(99,102,241,0.2);
    }

    .hr-chip-tag.mode-tournament {
        color: #c084fc;
        background: rgba(192,132,252,0.09);
        border: 1px solid rgba(192,132,252,0.18);
    }

    .hr-chip-tag.mode-sparring {
        color: #38bdf8;
        background: rgba(56,189,248,0.09);
        border: 1px solid rgba(56,189,248,0.18);
    }

    .hr-card-verdict {
        font-size: 0.77rem;
        color: #6e7fc4;
        margin-bottom: 0.85rem;
        font-style: italic;
        /* Contain text strictly */
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }

    /* ── Actions row ── */
    .hr-card-actions {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
        margin-top: auto;
        padding-top: 0.6rem;
    }

    .hr-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.76rem;
        font-weight: 600;
        padding: 0.4rem 0.85rem;
        border-radius: 5px;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.15s ease;
        cursor: pointer;
        background: none;
        white-space: nowrap;
        line-height: 1;
        font-family: inherit;
        flex-shrink: 0;
    }

    .hr-btn-primary {
        background: #4f5edd;
        border-color: #3d4bc9;
        color: #fff;
    }

    .hr-btn-primary:hover {
        background: #3d4bc9;
        color: #fff;
        box-shadow: 0 4px 12px rgba(79,94,221,0.4);
    }

    .hr-btn-ghost {
        border-color: #1e2535;
        color: #6e84a3;
    }

    .hr-btn-ghost:hover {
        border-color: #2d3566;
        color: #818cf8;
        background: rgba(99,102,241,0.08);
    }

    .hr-btn-resume {
        border-color: rgba(16,185,129,0.3);
        color: #34d399;
        background: rgba(16,185,129,0.07);
    }

    .hr-btn-resume:hover {
        background: rgba(16,185,129,0.14);
        color: #f0f4ff;
    }

    .hr-btn-delete {
        border-color: rgba(239, 68, 68, 0.3);
        color: #f87171;
        background: rgba(239, 68, 68, 0.08);
        padding: 0.4rem 0.6rem;
    }

    .hr-btn-delete:hover {
        border-color: rgba(239, 68, 68, 0.5);
        color: #ffffff;
        background: rgba(239, 68, 68, 0.22);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
    }

    /* ── Card aside (right, fixed width) ── */
    .hr-card-aside {
        flex-shrink: 0;
        width: 80px;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .hr-score-block {
        text-align: right;
        line-height: 1;
    }

    .hr-score-value {
        font-size: 2rem;
        font-weight: 800;
        color: #f0f4ff;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.03em;
        display: block;
    }

    .hr-score-denom {
        font-size: 0.7rem;
        color: #3c4d66;
        font-weight: 500;
    }

    .hr-status-badge {
        font-size: 0.6rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 0.2rem 0.55rem;
        border-radius: 20px;
        white-space: nowrap;
    }

    .hr-status-badge.done {
        color: #34d399;
        background: rgba(16,185,129,0.09);
        border: 1px solid rgba(16,185,129,0.2);
    }

    .hr-status-badge.pending {
        color: #fbbf24;
        background: rgba(245,158,11,0.09);
        border: 1px solid rgba(245,158,11,0.2);
    }

    .hr-empty {
        padding: 4rem 2rem;
        text-align: center;
        background: #0a0d16;
        border: 1px solid #1e2535;
        border-radius: 8px;
    }

    .hr-empty-filter {
        padding: 3rem 2rem;
        border-style: dashed;
        background: transparent;
    }

    .hr-empty-icon {
        width: 44px; height: 44px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
    }

    .hr-empty-icon svg {
        width: 20px;
        height: 20px;
    }

    .hr-empty-icon.tournament,
    .hr-empty-icon.sparring,
    .hr-empty-icon.search,
    .hr-empty-icon.none {
        background: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.2);
        color: #818cf8;
    }

    .hr-empty-title {
        font-size: 1rem;
        font-weight: 700;
        color: #f0f4ff;
        margin-bottom: 0.45rem;
    }

    .hr-empty-text {
        font-size: 0.84rem;
        color: #5c7090;
        line-height: 1.7;
        max-width: 340px;
        margin: 0 auto 1.5rem;
    }

    .hr-empty-hints {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        max-width: 300px;
        margin: 0 auto 1.5rem;
        text-align: left;
    }

    .hr-empty-hint {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        font-size: 0.78rem;
        color: #5c7090;
        line-height: 1.55;
    }

    .hr-empty-hint-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #2d3566;
        flex-shrink: 0;
        margin-top: 0.45rem;
    }

    .hr-filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        flex: 1;
        max-width: 480px;
    }

    .hr-search {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 6px;
        padding: 0.48rem 0.85rem;
        flex: 1;
        min-width: 0;
        transition: border-color 0.15s ease;
    }

    /* ── Responsive ── */
    @media (max-width: 640px) {
        .hr-page { padding: 1.5rem 0.85rem 4rem; }

        .hr-topbar { flex-direction: column; align-items: stretch; }
        .hr-new-btn { width: 100%; justify-content: center; }

        .hr-toolbar { flex-direction: column; align-items: stretch; gap: 0.75rem; }
        .hr-tabs { width: 100%; }
        .hr-tab { flex: 1; justify-content: center; }

        .hr-filter-group {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
            gap: 0.65rem;
        }

        .hr-search {
            width: 100%;
            max-width: 100%;
        }

        .hr-status-select {
            width: 100%;
        }

        /* Stack card vertically: score on top, body below */
        .hr-card {
            flex-direction: column;
            padding: 1rem 1rem 1rem 1.2rem;
        }

        .hr-card-aside {
            width: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.65rem;
            border-bottom: 1px solid #111827;
            margin-bottom: 0.1rem;
        }

        .hr-score-block { text-align: left; display: flex; align-items: baseline; gap: 2px; }
        .hr-score-value { font-size: 1.5rem; }
        .hr-card-motion { -webkit-line-clamp: 4; }
        .hr-card-verdict { white-space: normal; }
    }
</style>

<div class="hr-page" x-data="historyPage()">

    {{-- Top bar --}}
    <div class="hr-topbar">
        <h1 class="hr-title">Debate History</h1>
        <a href="{{ route('setup') }}" class="hr-new-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Debate
        </a>
    </div>

    {{-- Toolbar --}}
    <div class="hr-toolbar">
        <div class="hr-tabs" role="tablist" aria-label="Filter by debate format">
            <button type="button" role="tab" class="hr-tab"
                x-bind:class="tab === 'tournament' ? 'active' : ''"
                :aria-selected="tab === 'tournament'"
                x-on:click="tab = 'tournament'">
                Tournament
                <span class="hr-tab-count" x-text="tournamentCount">0</span>
            </button>
            <button type="button" role="tab" class="hr-tab"
                x-bind:class="tab === 'sparring' ? 'active' : ''"
                :aria-selected="tab === 'sparring'"
                x-on:click="tab = 'sparring'">
                Sparring
                <span class="hr-tab-count" x-text="sparringCount">0</span>
            </button>
        </div>

        <div class="hr-filter-group">
            <div class="hr-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" x-model="search" placeholder="Search by motion…" aria-label="Search debates by motion">
                <button type="button" class="hr-search-clear" x-show="search" x-cloak @click="search = ''" aria-label="Clear search">×</button>
            </div>

            <select class="hr-status-select" x-model="statusFilter" aria-label="Filter by status">
                <option value="all">All Status</option>
                <option value="finished">Finished</option>
                <option value="in_progress">In Progress</option>
            </select>
        </div>
    </div>

    @if($debates->isEmpty())

    <div class="hr-empty">
        <div class="hr-empty-icon none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" x2="8" y1="13" y2="13"/>
                <line x1="16" x2="8" y1="17" y2="17"/>
            </svg>
        </div>
        <div class="hr-empty-title">No sessions on record</div>
        <a href="{{ route('setup') }}" class="hr-new-btn" style="display:inline-flex;width:auto;">Start First Debate</a>
    </div>

    @else

    <div class="hr-list">

        @foreach($debates as $debate)
        @php
            $motionText = $debate->motion->textFor($debate->language);
            $adj        = $debate->adjudication;
            $isBn       = $debate->language === 'bn';
            $fontStyle  = $isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
        @endphp

        <div class="hr-card {{ $adj ? 'is-complete' : 'is-pending' }}"
             data-mode="{{ $debate->mode }}"
             data-status="{{ $adj ? 'finished' : 'in_progress' }}"
             data-motion="{{ $motionText }}"
             x-show="matches($el)">

            {{-- Body (left, grows) --}}
            <div class="hr-card-body">

                <p class="hr-card-motion" style="{{ $fontStyle }}">{{ $motionText }}</p>

                <div class="hr-card-meta">
                    <span class="hr-chip-tag mode-{{ $debate->mode }}">{{ ucfirst($debate->mode) }}</span>
                    <span class="hr-chip-tag side">{{ ucfirst($debate->user_side) }}</span>
                    <span class="hr-chip-dot"></span>
                    <span class="hr-chip">vs. {{ $debate->persona->name }}</span>
                    <span class="hr-chip-dot"></span>
                    <span class="hr-chip">{{ strtoupper($debate->language) }}</span>
                    <span class="hr-chip-dot"></span>
                    <span class="hr-chip">{{ $debate->created_at->format('d M Y') }}</span>
                </div>

                @if($adj)
                <div class="hr-card-verdict" title="{{ $adj->verdict }}" style="{{ $fontStyle }}">
                    "{{ $adj->verdict }}"
                </div>
                @endif

                <div class="hr-card-actions">
                    @if($adj)
                    <a href="{{ route('debates.feedback', $debate->id) }}" class="hr-btn hr-btn-primary">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        View Report
                    </a>
                    <a href="{{ route('debates.show', $debate->id) }}" class="hr-btn hr-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Arena
                    </a>
                    @else
                    <a href="{{ route('debates.show', $debate->id) }}" class="hr-btn hr-btn-resume">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Resume
                    </a>
                    @endif

                    <form action="{{ route('debates.destroy', $debate->id) }}" method="POST"
                          x-ref="deleteForm{{ $loop->index }}"
                          style="display:contents;"
                          @submit.prevent="confirmAction({
                              title: 'Delete Debate Session',
                              message: 'Are you sure you want to permanently delete this debate session? This action cannot be undone.',
                              confirmText: 'Delete Session'
                          }).then(() => $refs.deleteForm{{ $loop->index }}.submit())">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="hr-btn hr-btn-delete" title="Delete session">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </form>
                </div>

            </div>

            {{-- Aside (right, fixed) --}}
            <div class="hr-card-aside">
                @if($adj)
                <div class="hr-score-block">
                    <span class="hr-score-value">{{ $adj->total_score }}</span>
                    <span class="hr-score-denom">/100</span>
                </div>
                <span class="hr-status-badge done">Complete</span>
                @else
                <span class="hr-status-badge pending">In Progress</span>
                @endif
            </div>

        </div>
        @endforeach

    </div>

    {{-- Filtered empty states --}}
    <div x-show="visibleCount === 0" x-cloak role="status">

        {{-- Search: no match --}}
        <div class="hr-empty hr-empty-filter" x-show="search.trim() !== ''">
            <div class="hr-empty-icon search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div class="hr-empty-title">No debates match your search</div>
            <button type="button" class="hr-new-btn" style="display:inline-flex;width:auto;border:none;cursor:pointer;" @click="search = ''">Clear Search</button>
        </div>

        {{-- Tournament: no debates --}}
        <div class="hr-empty hr-empty-filter" x-show="search.trim() === '' && tab === 'tournament'">
            <div class="hr-empty-icon tournament">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
            </div>
            <div class="hr-empty-title">No tournament debates yet</div>
            <a href="{{ route('setup') }}" class="hr-new-btn" style="display:inline-flex;width:auto;">Start Tournament Debate</a>
        </div>

        {{-- Sparring: no debates --}}
        <div class="hr-empty hr-empty-filter" x-show="search.trim() === '' && tab === 'sparring'">
            <div class="hr-empty-icon sparring">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3z"/><path d="M5 3v4"/><path d="M3 5h4"/><path d="M19 17v4"/><path d="M17 19h4"/></svg>
            </div>
            <div class="hr-empty-title">No sparring sessions yet</div>
            <a href="{{ route('setup') }}" class="hr-new-btn" style="display:inline-flex;width:auto;">Start Sparring Session</a>
        </div>

    </div>

    @endif

</div>

<script>
    function historyPage() {
        return {
            tab: '{{ $defaultTab }}',
            search: '',
            statusFilter: 'all',
            visibleCount: {{ $debates->count() }},
            tournamentCount: {{ $tournamentCount }},
            sparringCount: {{ $sparringCount }},

            init() {
                this.$watch('tab', () => this.refreshCount());
                this.$watch('search', () => this.refreshCount());
                this.$watch('statusFilter', () => this.refreshCount());
                this.refreshCount();
            },

            matches(el) {
                if ((el.dataset.mode || '') !== this.tab) return false;
                if (this.statusFilter !== 'all' && (el.dataset.status || '') !== this.statusFilter) return false;
                const q = this.search.trim().toLowerCase();
                if (!q) return true;
                return (el.dataset.motion || '').toLowerCase().includes(q);
            },

            refreshCount() {
                requestAnimationFrame(() => {
                    const rows = Array.from(document.querySelectorAll('.hr-card'));
                    const q = this.search.trim().toLowerCase();
                    const status = this.statusFilter;

                    const matchesFilter = (el) => {
                        const searchMatch = !q || (el.dataset.motion || '').toLowerCase().includes(q);
                        const statusMatch = status === 'all' || (el.dataset.status || '') === status;
                        return searchMatch && statusMatch;
                    };

                    this.tournamentCount = rows.filter(el => el.dataset.mode === 'tournament' && matchesFilter(el)).length;
                    this.sparringCount = rows.filter(el => el.dataset.mode === 'sparring' && matchesFilter(el)).length;

                    this.visibleCount = this.tab === 'tournament' ? this.tournamentCount : this.sparringCount;
                });
            },
        };
    }
</script>

@endsection

@extends('layouts.app')

@section('title', ucfirst($currentRound?->phase ?? 'Opening') . ' Phase — Rostrum')

@section('content')

    @php
        $motionText = $debate->motion->textFor($debate->language);
        $aiSide = $debate->aiSide();
        $lang = $debate->language;
        $isBn = $lang === 'bn';
        $fontStyle = $isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
        $phaseLabels = [
            'opening' => 'Opening',
            'rebuttal' => 'Rebuttal',
            'closing' => 'Closing',
        ];
    @endphp

    <style>
        /* ── Rostrum Debate Arena — Professional Layout ── */
        .debate-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            height: calc(100vh - 65px);
            overflow: hidden;
            background: var(--color-bg);
        }

        /* ── Transcript Column ── */
        .transcript-col {
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--color-border);
            overflow: hidden;
            background: var(--color-bg);
        }

        .transcript-header {
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-surface);
            flex-shrink: 0;
            z-index: 10;
        }

        .transcript-feed {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem 2.5rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            scroll-behavior: smooth;
        }

        .transcript-feed::-webkit-scrollbar { width: 4px; }
        .transcript-feed::-webkit-scrollbar-track { background: transparent; }
        .transcript-feed::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 2px; }
        .transcript-feed::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }

        /* ── Sidebar Column ── */
        .podium-col {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--color-surface);
        }

        .podium-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-surface);
            flex-shrink: 0;
        }

        .podium-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        /* ── Phase Divider ── */
        .phase-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0.5rem 0;
        }

        .phase-divider::before,
        .phase-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--color-border);
        }

        .phase-divider span {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #7b91b3;
        }

        /* ── Turn Rows ── */
        .turn-row {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 0.5rem;
            animation: fadeUp 0.2s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .turn-row.user { align-items: flex-end; }
        .turn-row.ai { align-items: flex-start; }

        .turn-meta {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: #7b91b3;
            padding-inline: 0.25rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        /* ── Speech Bubbles ── */
        .bubble {
            max-width: 80%;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            line-height: 1.7;
            word-break: break-word;
            position: relative;
        }

        .bubble.user {
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-text);
            border-bottom-right-radius: var(--radius-sm);
        }

        .bubble.ai {
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            color: var(--color-text);
            border-bottom-left-radius: var(--radius-sm);
        }

        .bubble.thinking {
            background: transparent;
            border: 1px dashed var(--color-border-hover);
            color: var(--color-text-muted);
        }

        /* Typing dots */
        .typing-dots span {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--color-text-dim);
            margin: 0 2px;
            animation: dotPulse 1.4s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes dotPulse {
            0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
            40% { opacity: 1; transform: scale(1); }
        }

        /* ── Turn Actions ── */
        .turn-actions {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding-inline: 0.25rem;
            margin-top: 0.1rem;
        }

        /* ── Mic Control ── */
        .mic-orb {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--color-border);
            transition: all 0.2s ease;
            outline: none;
            position: relative;
            background: var(--color-surface-2);
        }

        .mic-orb.idle {
            background: var(--color-accent);
            border-color: var(--color-accent);
            box-shadow: 0 2px 12px rgba(99, 102, 241, 0.3);
        }

        .mic-orb.idle:hover {
            transform: scale(1.04);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.45);
        }

        .mic-orb.recording {
            background: var(--color-danger);
            border-color: var(--color-danger);
            animation: micPulse 1.5s infinite;
        }

        @keyframes micPulse {
            to { box-shadow: 0 0 0 16px rgba(239, 68, 68, 0); }
        }

        .mic-orb.processing {
            background: var(--color-surface-2);
            border-color: var(--color-border);
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* ── Status Pill ── */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.8rem;
            border-radius: var(--radius-pill);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .status-pill.your-turn {
            background: rgba(99, 102, 241, 0.1);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .status-pill.recording {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-pill.processing {
            background: rgba(245, 158, 11, 0.1);
            color: #fcd34d;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-pill.ai-thinking {
            background: rgba(16, 185, 129, 0.08);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        /* ── Timer ── */
        .timer-display {
            font-size: 2.25rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.03em;
            line-height: 1;
            color: var(--color-text);
            transition: color 0.3s;
        }

        .timer-display.warning { color: var(--color-danger); }

        /* ── Info Rows ── */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            padding: 0.35rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .info-row:last-child { border-bottom: none; }

        .info-row span:first-child {
            color: #7b91b3;
            font-weight: 500;
        }

        .info-row span:last-child {
            font-weight: 600;
            color: #cbd5e1;
        }

        /* ── Rewrite Panel ── */
        .rewrite-panel {
            display: none;
            margin-top: 0.75rem;
            margin-bottom: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
            background: var(--color-surface-2);
            overflow: hidden;
        }

        .rewrite-panel.visible { display: block; }

        /* ── Timeline Stepper ── */
        .timeline-container {
            display: flex;
            align-items: center;
            width: 100%;
            margin-top: 0.85rem;
            padding: 0.6rem 0.85rem;
            background: var(--color-bg);
            border-radius: var(--radius);
            border: 1px solid var(--color-border);
        }

        .timeline-step {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--color-text-dim);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .timeline-step.completed { color: var(--color-success); }
        .timeline-step.active { color: var(--color-accent); font-weight: 700; }

        .timeline-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 700;
            border: 1px solid var(--color-border);
            background: var(--color-surface);
            color: var(--color-text-dim);
            transition: all 0.3s ease;
        }

        .timeline-step.completed .timeline-icon {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.35);
            color: var(--color-success);
        }

        .timeline-step.active .timeline-icon {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
            color: var(--color-accent);
            box-shadow: 0 0 8px rgba(99, 102, 241, 0.25);
        }

        .timeline-line {
            flex: 1;
            height: 1px;
            background: var(--color-border);
            margin-inline: 0.5rem;
        }

        .timeline-line.completed { background: var(--color-success); opacity: 0.5; }
        .timeline-line.active-progress { background: linear-gradient(90deg, rgba(16,185,129,0.4), var(--color-accent)); }

        /* ── Section Label ── */
        .section-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #7b91b3;
        }

        /* ── Responsive: stack transcript + podium on small screens ── */
        @media (max-width: 900px) {
            .debate-layout {
                grid-template-columns: 1fr;
                height: auto;
                min-height: calc(100vh - 65px);
                overflow: visible;
            }

            .transcript-col {
                border-right: none;
                border-bottom: 1px solid var(--color-border);
                min-height: 45vh;
            }

            .transcript-feed {
                padding: 1.25rem 1.25rem 2rem;
                max-height: 60vh;
            }

            .podium-col {
                border-top: 1px solid var(--color-border);
            }
        }
    </style>

    <div x-data="debateArena()" x-init="init()" class="debate-layout" @keydown.window="onGlobalKey($event)">

        {{-- ═══════════════════════════════════════════
         LEFT: TRANSCRIPT COLUMN
         ═══════════════════════════════════════════ --}}
        <div class="transcript-col">

            {{-- Top header: motion + badges + timeline --}}
            <div class="transcript-header">
                <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                    <span
                        style="font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; padding:0.2rem 0.55rem; background:var(--color-accent); color:#fff; border-radius:var(--radius-sm); flex-shrink:0; margin-top:3px;">MOTION</span>
                    <p
                        style="{{ $fontStyle }} font-size:0.95rem; font-weight:600; line-height:1.5; color:var(--color-text); flex:1; margin:0; letter-spacing:-0.01em;">
                        {{ $motionText }}
                    </p>
                </div>
                <div style="display:flex; gap:0.4rem; margin-top:0.75rem; flex-wrap:wrap; align-items:center;">
                    <span class="badge badge-blue">
                        You: <strong style="text-transform:capitalize; margin-left:2px; color:var(--color-text);">{{ $debate->user_side }}</strong>
                    </span>
                    <span class="badge badge-accent">
                        AI: <strong style="text-transform:capitalize; margin-left:2px; color:var(--color-text);">{{ $aiSide }}</strong>
                    </span>
                    <span class="badge badge-success">
                        {{ $debate->persona->name }}
                    </span>
                    @if ($debate->mode === 'sparring')
                        <span class="badge" style="background:rgba(245,158,11,0.1); color:#fcd34d; border:1px solid rgba(245,158,11,0.2);">
                            Sparring · Turn <span x-text="turnCount" style="margin-left:2px; color:var(--color-text);">{{ $rounds->flatMap->turns->count() }}</span>
                        </span>
                    @endif
                </div>

                @if ($debate->mode !== 'sparring')
                    {{-- Horizontal Dynamic Timeline --}}
                    <div class="timeline-container">
                        @foreach ($phases as $i => $phase)
                            @php
                                $phaseRound = $rounds->firstWhere('phase', $phase);
                                $isDone = $phaseRound && $phaseRound->turns->count() >= 2;
                                $isActive = $currentRound?->phase === $phase;
                                $prevRound = $i > 0 ? $rounds->firstWhere('phase', $phases[$i - 1]) : null;
                                $prevDone = $prevRound && $prevRound->turns->count() >= 2;
                            @endphp

                            @if ($i > 0)
                                <div class="timeline-line {{ $isDone || ($isActive && $prevDone) ? ($isDone ? 'completed' : 'active-progress') : '' }}"></div>
                            @endif

                            <div class="timeline-step {{ $isDone ? 'completed' : ($isActive ? 'active' : '') }}">
                                <span class="timeline-icon">
                                    @if ($isDone)
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    @elseif($isActive)
                                        <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </span>
                                <span>{{ $phaseLabels[$phase] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Transcript feed --}}
            <div class="transcript-feed" id="transcript-feed">

                {{-- Server-rendered existing turns --}}
                @foreach ($rounds as $round)
                    @if ($round->phase)
                        <div class="phase-divider">
                            <span
                                style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:var(--color-text-muted);">
                                {{ $phaseLabels[$round->phase] ?? $round->phase }}
                            </span>
                        </div>
                    @endif

                    @foreach ($round->turns as $turn)
                        <div class="turn-row {{ $turn->speaker }}">

                            <div class="turn-meta">
                                @if ($turn->speaker === 'user')
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    You ({{ ucfirst($debate->user_side) }})
                                    @if ($turn->transcript && !str_starts_with($turn->transcript, '['))
                                        <span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#34d399; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2);">Transcribed by ElevenLabs Scribe</span>
                                    @endif
                                @else
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <rect width="18" height="18" x="3" y="3" rx="2" />
                                        <path d="M9 8h6" />
                                        <path d="M9 12h6" />
                                        <path d="M9 16h6" />
                                    </svg>
                                    {{ $debate->persona->name }}
                                    <span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#818cf8; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);">Logic: Gemini 3.6 Flash</span>
                                    <span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#818cf8; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);">Voice: ElevenLabs ({{ $debate->persona->voiceName() }})</span>
                                    @if ($turn->ai_move_type)
                                        <span class="badge badge-accent"
                                            style="font-size:0.6rem; padding:0.08rem 0.3rem; text-transform:capitalize;">{{ str_replace('_', ' ', $turn->ai_move_type) }}</span>
                                    @endif
                                @endif
                            </div>

                            <div class="bubble {{ $turn->speaker }}" style="{{ $fontStyle }}">{{ $turn->transcript }}
                            </div>

                            <div class="turn-actions">
                                @if ($turn->audio_path)
                                    <audio id="audio-{{ $turn->id }}" src="{{ $turn->audio_path }}"
                                        preload="none"></audio>
                                    <button type="button" class="btn btn-secondary btn-sm audio-btn"
                                        onclick="toggleAudio('audio-{{ $turn->id }}', this)"
                                        aria-label="Listen to this speech"
                                        style="padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px;">
                                        <svg class="play-icon" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                                            <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
                                        </svg>
                                        <svg class="stop-icon" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" style="display:none;">
                                            <rect x="6" y="6" width="12" height="12" rx="1" fill="currentColor"/>
                                        </svg>
                                        <span class="btn-text">Listen</span>
                                    </button>
                                @endif

                                @if ($turn->speaker === 'user' && !$turn->rewrite)
                                    <button type="button" class="btn btn-outline btn-sm"
                                        id="btn-rewrite-{{ $turn->id }}"
                                        onclick="requestRewrite('{{ $turn->id }}', this, '{{ $debate->id }}')"
                                        style="padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px;">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path
                                                d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z" />
                                        </svg>
                                        Improve argument
                                    </button>
                                @endif
                            </div>

                            @if ($turn->speaker === 'user')
                                <div id="rewrite-wrapper-{{ $turn->id }}" style="width:100%;">
                                    @if ($turn->rewrite)
                                        <x-rewrite-panel :rewrite="$turn->rewrite" :isBn="$isBn" :fontStyle="$fontStyle"
                                            :visible="true" />
                                    @else
                                        <div id="rewrite-panel-{{ $turn->id }}" class="rewrite-panel"></div>
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach
                @endforeach

                @php
                    $lastTurn = $rounds->flatMap->turns->last();
                    $pendingAiTurn = $lastTurn && $lastTurn->speaker === 'user' && !$isComplete;
                @endphp

                @if ($pendingAiTurn)
                    <div id="status-ai-error-row" class="turn-row ai">
                        <div class="turn-meta">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" />
                                <path d="M9 8h6" />
                                <path d="M9 12h6" />
                                <path d="M9 16h6" />
                            </svg>
                            {{ $debate->persona->name }}
                        </div>
                        <div class="bubble ai"
                            style="border:1px solid rgba(239,68,68,0.25); background:rgba(239,68,68,0.05); display:flex; flex-direction:column; gap:0.5rem;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" style="flex-shrink:0; color:var(--color-danger);">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <span style="font-size:0.8rem; font-weight:500; color:var(--color-text-muted);">The opponent has not responded to your speech yet.</span>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; padding-top:0.4rem; border-top:1px solid rgba(239,68,68,0.15);">
                                <span style="font-size:0.7rem; color:var(--color-text-dim);">Your speech was saved.</span>
                                <button type="button" class="btn btn-sm"
                                    onclick="window.podiumComponent?.retryAiGeneration()"
                                    style="background:var(--color-danger); color:#fff; border:none; padding:0.2rem 0.6rem; font-size:0.7rem; font-weight:600; display:inline-flex; align-items:center; gap:4px; border-radius:var(--radius-sm);">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M21.5 2v6h-6" />
                                        <path d="M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                                    </svg>
                                    Retry
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Dynamic turns injected here by JS --}}
                <div id="dynamic-turns" aria-live="polite"></div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════
         RIGHT: PODIUM CONTROL PANEL
         ═══════════════════════════════════════════ --}}
        <div class="podium-col">

            <div class="podium-header">
                <p class="section-label" style="margin-bottom:0.3rem;">Debate Podium</p>
                @if ($debate->mode === 'sparring')
                    <div style="font-size:0.88rem; font-weight:700; color:var(--color-text); {{ $fontStyle }}">
                        Free Practice Round
                    </div>
                @else
                    <div style="font-size:0.88rem; font-weight:700; color:var(--color-text); {{ $fontStyle }}">
                        {{ $phaseLabels[$currentRound?->phase ?? 'opening'] ?? 'Active Round' }} Phase
                    </div>
                @endif
            </div>

            <div class="podium-body">

                {{-- ── DEBATE COMPLETE STATE ── --}}
                @if ($isComplete)
                    <div style="text-align:center; padding-block:1.5rem;">
                        <div style="width:48px; height:48px; border-radius:50%; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <h2 style="font-size:1rem; font-weight:700; margin-bottom:0.4rem; {{ $fontStyle }}">Debate Complete</h2>
                        <p style="font-size:0.78rem; color:#7b91b3; margin-bottom:1.25rem; line-height:1.5;">This session has ended. Review your AI adjudication scorecard.</p>
                        @if ($debate->adjudication)
                            <a href="{{ route('debates.feedback', $debate->id) }}" id="btn-get-feedback" class="btn btn-primary" style="width:100%; {{ $fontStyle }}">
                                View Adjudication Report →
                            </a>
                        @else
                            <form action="{{ route('debates.adjudicate', $debate->id) }}" method="POST">
                                @csrf
                                <button type="submit" id="btn-get-feedback" class="btn btn-primary"
                                    style="width:100%; {{ $fontStyle }}">
                                    View Adjudication →
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- ── LIVE DEBATE PODIUM (Alpine.js reactive) ── --}}
                @else
                    <div x-data="podium(@js([
    'debateId' => $debate->id,
    'currentPhase' => $currentRound?->phase ?? 'opening',
    'phaseDuration' => $phaseDuration,
    'lang' => $lang,
    'isBn' => $isBn,
    'csrfToken' => csrf_token(),
    'submitUrl' => route('debates.turns.submit', $debate->id),
    'nextSpeaker' => $nextSpeaker,
    'personaName' => $debate->persona->name,
    'userSide' => $debate->user_side,
    'aiSide' => $aiSide,
]))" x-init="init()"
                        style="display:flex; flex-direction:column; gap:1rem;">

                        {{-- Status pill --}}
                        <div style="display:flex; justify-content:center;">
                            <span class="status-pill" role="status" aria-live="polite"
                                :class="{
                                    'your-turn': state === 'idle',
                                    'recording': state === 'recording',
                                    'processing': state === 'transcribing',
                                    'ai-thinking': state === 'ai_thinking'
                                }">
                                <template x-if="state === 'idle'">
                                    <span style="display:flex; align-items:center; gap:0.35rem;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                                            <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                                            <line x1="12" x2="12" y1="19" y2="22" />
                                        </svg>
                                        Your Turn
                                    </span>
                                </template>
                                <template x-if="state === 'recording'">
                                    <span style="display:flex; align-items:center; gap:0.35rem;">
                                        <span
                                            style="width:7px; height:7px; border-radius:50%; background:var(--color-danger); animation:micPulse 1s infinite;"></span>
                                        Recording
                                    </span>
                                </template>
                                <template x-if="state === 'transcribing'">
                                    <span style="display:flex; align-items:center; gap:0.35rem;">
                                        <span class="spinner" style="width:10px; height:10px; border-width:2px;"></span>
                                        Transcribing…
                                    </span>
                                </template>
                                <template x-if="state === 'ai_thinking'">
                                    <span style="display:flex; align-items:center; gap:0.35rem;">
                                        <span class="spinner" style="width:10px; height:10px; border-width:2px;"></span>
                                        Opponent responding…
                                    </span>
                                </template>
                            </span>
                        </div>

                        {{-- Timer (shows only while recording) --}}
                        <div x-show="state === 'recording'" x-cloak style="text-align:center;">
                            <div class="timer-display" :class="timeLeft <= 30 ? 'warning' : ''"
                                x-text="formatTime(timeLeft)" aria-hidden="true"></div>
                            <p style="font-size:0.68rem; color:var(--color-text-dim); margin-top:0.25rem;"
                                x-text="timeLeft <= 30 ? 'Under 30 seconds — wrap up your point' : 'Time remaining'"></p>
                        </div>

                        {{-- Mic orb (shows when it's user's turn or recording) --}}
                        <div x-show="state === 'idle' || state === 'recording'" x-cloak
                            style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; gap:2rem; margin-block:1.25rem;">
                            <button class="mic-orb" style="margin:0 auto;"
                                :class="{ 'idle': state==='idle', 'recording': state==='recording' }"
                                :aria-label="state === 'recording' ? 'Stop recording' : 'Start recording (press Space)'"
                                :aria-pressed="state === 'recording'"
                                @click="toggleRecording" :disabled="state !== 'idle' && state !== 'recording'">
                                <template x-if="state !== 'recording'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                                        <line x1="12" x2="12" y1="19" y2="22" />
                                    </svg>
                                </template>
                                <template x-if="state === 'recording'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                                        viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="6" y="6" width="12" height="12" rx="2" fill="white" />
                                    </svg>
                                </template>
                            </button>
                            <p style="font-size:0.75rem; color:#7b91b3; text-align:center; margin:0; margin-top:0.35rem; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                                <span x-text="state === 'recording' ? 'Tap or press' : 'Tap or press'"></span>
                                <kbd style="display:inline-flex; align-items:center; padding:0.1rem 0.4rem; font-size:0.68rem; font-weight:700; font-family:inherit; color:#e2e8f0; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.2); border-radius:3px; box-shadow:0 1px 2px rgba(0,0,0,0.4); text-transform:uppercase; letter-spacing:0.04em;">Space</kbd>
                                <span x-text="state === 'recording' ? 'to stop recording' : 'to start speaking'"></span>
                            </p>
                        </div>

                        {{-- AI thinking spinner --}}
                        <div x-show="state === 'ai_thinking'" x-cloak
                            style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; gap:0.85rem; margin-block:1.5rem;">
                            <div class="spinner" style="width:36px; height:36px; margin:0 auto;"></div>
                            <p style="font-size:0.8rem; font-weight:500; color:var(--color-text-dim); text-align:center; margin:0;"
                                x-text="aiThinkingMsg"></p>
                        </div>

                        {{-- Retry box --}}
                        <div x-show="showRetry" x-cloak role="alert"
                            style="padding:0.75rem; background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); border-radius:var(--radius-lg); text-align:center;">
                            <p style="font-size:0.75rem; color:var(--color-text-muted); margin-bottom:0.5rem;">Couldn't capture your speech clearly.</p>
                            <button type="button" class="btn btn-danger btn-sm"
                                @click="showRetry=false; state='idle'">Try Again</button>
                        </div>

                        {{-- Error box --}}
                        <div x-show="errorMsg" x-cloak role="alert"
                            style="padding:0.75rem; background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); border-radius:var(--radius-lg); text-align:center;">
                            <p style="font-size:0.75rem; color:var(--color-text-muted); margin-bottom:0.5rem;" x-text="errorMsg"></p>
                            <button type="button" class="btn btn-secondary btn-sm"
                                @click="errorMsg=''; state='idle'">Dismiss</button>
                        </div>

                        @if ($debate->mode === 'sparring' && ! $debate->adjudication)
                            {{-- End round: sparring has no fixed phases — the user ends it anytime --}}
                            <div style="padding:0.75rem; background:rgba(99,102,241,0.05); border:1px solid rgba(99,102,241,0.2); border-radius:var(--radius-lg); text-align:center;">
                                <p style="font-size:0.72rem; color:var(--color-text-dim); margin-bottom:0.6rem; line-height:1.5;">
                                    No fixed phases here — trade quick speeches and end the round anytime to receive your adjudication report.
                                </p>
                                <form action="{{ route('debates.adjudicate', $debate->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" id="btn-end-sparring" class="btn btn-primary btn-sm" style="width:100%;">
                                        End Round &amp; Get Feedback
                                    </button>
                                </form>
                            </div>
                        @endif

                    </div>
                @endif



                {{-- Match settings summary --}}
                <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--color-border);">
                    <p class="section-label" style="margin-bottom:0.5rem;">Match Info</p>
                    <div class="info-row"><span>Persona</span><span>{{ $debate->persona->name }}</span></div>
                    <div class="info-row"><span>Voice</span><span>ElevenLabs ({{ $debate->persona->voiceName() }})</span></div>
                    <div class="info-row"><span>Difficulty</span><span style="text-transform:capitalize;">{{ str_replace('_', ' ', $debate->difficulty) }}</span></div>
                    <div class="info-row"><span>Format</span><span style="text-transform:capitalize;">{{ $debate->mode }}</span></div>
                    <div class="info-row"><span>Language</span><span style="text-transform:uppercase;">{{ $debate->language }}</span></div>
                </div>

                {{-- AI Engine attribution badge --}}
                <div style="margin-top:1rem; padding:0.65rem 0.75rem; background:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.18); border-radius:var(--radius-lg, 4px); font-size:0.68rem; color:#7b91b3; display:flex; flex-direction:column; gap:3px;">
                    <div style="display:flex; align-items:center; gap:5px; font-weight:700; color:#818cf8; letter-spacing:0.04em; text-transform:uppercase;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                        AI Stack
                    </div>
                    <div>Logic: <strong>Gemini 3.6 Flash</strong></div>
                    <div>Speech: <strong>ElevenLabs Scribe &amp; Voice</strong></div>
                </div>

            </div>
        </div>

    </div>

    <script>
        /* ─────────────────────────────────────────────────────────
           MAIN ARENA COMPONENT (debateArena)
           Exposes: appendTurn(), showAiThinking(), hideThinking()
           ───────────────────────────────────────────────────────── */
        function debateArena() {
            return {
                turnCount: {{ $rounds->flatMap->turns->count() }},
                lang: '{{ $lang }}',

                init() {
                    window.debateArena = this;
                    this.pinned = true;
                    const feed = document.getElementById('transcript-feed');
                    if (feed) {
                        feed.addEventListener('scroll', () => {
                            const atBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight < 60;
                            if (!atBottom && this.pinned) this.pinned = false;
                            else if (atBottom && !this.pinned) this.pinned = true;
                        }, { passive: true });
                    }
                    this.scrollFeed();
                },

                // Spacebar toggles recording (ignored while typing in a field
                // or when a button/link has focus — Space would otherwise
                // double-trigger the focused control)
                onGlobalKey(e) {
                    const tag = (e.target.tagName || '').toLowerCase();
                    if (tag === 'input' || tag === 'textarea' || tag === 'select'
                        || tag === 'button' || tag === 'a' || e.target.isContentEditable) return;

                    if (e.key === ' ') {
                        e.preventDefault();
                        window.podiumComponent?.toggleRecording();
                    }
                },

                scrollFeed() {
                    const feed = document.getElementById('transcript-feed');
                    if (!feed || !this.pinned) return;
                    feed.scrollTop = feed.scrollHeight;
                },

                // Show user speech bubble with local audio player & transcribing state
                showUserTranscribing(localAudioUrl = null) {
                    this.removeStatusBubbles();
                    this.tempAudioUrl = localAudioUrl;
                    const container = document.getElementById('dynamic-turns');
                    if (!container) return;
                    const row = document.createElement('div');
                    row.id = 'status-user-transcribing-row';
                    row.className = 'turn-row user';

                    const audioButton = localAudioUrl ? `
                <audio id="audio-temp-user" src="${localAudioUrl}" preload="auto"></audio>
                <button type="button" class="btn btn-secondary btn-sm audio-btn"
                        onclick="toggleAudio('audio-temp-user', this)"
                        style="padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px;">
                    <svg class="play-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        <svg class="stop-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><rect x="6" y="6" width="12" height="12" rx="1" fill="currentColor"/></svg>
                    <span class="btn-text">Listen to recording</span>
                </button>` : '';

                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    You ({{ ucfirst($debate->user_side) }})
                </div>
                <div class="bubble user thinking" style="display:flex; align-items:center; gap:8px;">
                    <span class="spinner" style="width:11px; height:11px; border-width:2px;"></span>
                    <span>Transcribing your speech…</span>
                </div>
                <div class="turn-actions">${audioButton}</div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.scrollFeed();
                },

                // Show a ChatGPT-style "Thinking / Generating response" bubble in chat feed
                showAiThinking(customMsg = null) {
                    const msg = customMsg || 'Thinking & crafting response…';
                    
                    // If an AI error row is currently displayed, replace its contents in-place!
                    const existingErrRow = document.getElementById('status-ai-error-row');
                    if (existingErrRow) {
                        existingErrRow.id = 'status-ai-thinking-row';
                        existingErrRow.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    {{ $debate->persona->name }}
                </div>
                <div class="bubble ai thinking" style="display:flex; align-items:center; gap:8px;">
                    <span class="spinner" style="width:11px; height:11px; border-width:2px;"></span>
                    <span>${msg}</span>
                    <span class="typing-dots" style="margin-left:2px;"><span></span><span></span><span></span></span>
                </div>`;
                        this.pinned = true;
                        this.scrollFeed();
                        return;
                    }

                    this.removeStatusBubbles();
                    const container = document.getElementById('dynamic-turns');
                    if (!container) return;
                    const row = document.createElement('div');
                    row.id = 'status-ai-thinking-row';
                    row.className = 'turn-row ai';
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    {{ $debate->persona->name }}
                </div>
                <div class="bubble ai thinking" style="display:flex; align-items:center; gap:8px;">
                    <span class="spinner" style="width:11px; height:11px; border-width:2px;"></span>
                    <span>${msg}</span>
                    <span class="typing-dots" style="margin-left:2px;"><span></span><span></span><span></span></span>
                </div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.scrollFeed();
                },

                // Show AI-side error bubble — Gemini failure, independent of STT
                showAiError(message, retryCallback = null) {
                    this.removeStatusBubbles();
                    const container = document.getElementById('dynamic-turns');
                    if (!container) return;

                    // Remove existing error bubble if retrying
                    const oldErr = document.getElementById('status-ai-error-row');
                    if (oldErr) oldErr.remove();

                    const row = document.createElement('div');
                    row.id = 'status-ai-error-row';
                    row.className = 'turn-row ai';
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    {{ $debate->persona->name }}
                </div>
                <div class="bubble ai" style="border:1px solid rgba(239,68,68,0.25); background:rgba(239,68,68,0.05); display:flex; flex-direction:column; gap:0.5rem;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; color:var(--color-danger);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span style="font-size:0.8rem; font-weight:500; color:var(--color-text-muted);">${message}</span>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; padding-top:0.4rem; border-top:1px solid rgba(239,68,68,0.15);">
                        <span style="font-size:0.7rem; color:var(--color-text-dim);">Your speech was saved.</span>
                        <button type="button" id="btn-retry-ai-response" class="btn btn-sm" style="background:var(--color-danger); color:#fff; border:none; padding:0.2rem 0.6rem; font-size:0.7rem; font-weight:600; display:inline-flex; align-items:center; gap:4px; border-radius:var(--radius-sm);">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6"/><path d="M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                            Retry
                        </button>
                    </div>
                </div>`;
                    container.appendChild(row);

                    if (typeof retryCallback === 'function') {
                        document.getElementById('btn-retry-ai-response')?.addEventListener('click', () => {
                            retryCallback();
                        });
                    }

                    this.scrollFeed();
                },

                // Show a small non-blocking STT error note on the user side
                showSttError(message) {
                    const container = document.getElementById('dynamic-turns');
                    if (!container) return;
                    const note = document.createElement('div');
                    note.className = 'turn-row user';
                    note.innerHTML =
                        `
                <div class="turn-meta" style="opacity:0.5;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Note
                </div>
                <div style="font-size:0.72rem; color:var(--color-text-dim); padding:0.3rem 0.6rem; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:var(--radius-lg);">${message}</div>`;
                    this.pinned = true;
                    container.appendChild(note);
                    this.scrollFeed();
                },

                removeStatusBubbles() {
                    if (this.tempAudioUrl) {
                        URL.revokeObjectURL(this.tempAudioUrl);
                        this.tempAudioUrl = null;
                    }
                    const el1 = document.getElementById('status-user-transcribing-row');
                    if (el1) el1.remove();
                    const el2 = document.getElementById('status-ai-thinking-row');
                    if (el2) el2.remove();
                },

                // Inject user bubble optimistically BEFORE submitting to server
                appendUserBubble(transcript) {
                    const container = document.getElementById('dynamic-turns');
                    if (!container) return;
                    const isBn = this.lang === 'bn';
                    const fontStyle = isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
                    const row = document.createElement('div');
                    row.id = 'optimistic-user-turn';
                    row.className = 'turn-row user';
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    You ({{ ucfirst($debate->user_side) }})
                </div>
                <div class="bubble user" style="${fontStyle}">${this.escapeHtml(transcript)}</div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.scrollFeed();
                },

                // Replace optimistic user bubble with full turn (with audio/rewrite buttons)
                finalizeUserTurn(data) {
                    const existing = document.getElementById('optimistic-user-turn');
                    if (existing) existing.remove();

                    const container = document.getElementById('dynamic-turns');
                    const isBn = this.lang === 'bn';
                    const fontStyle = isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
                    const uid = data.user_turn_id || ('u-' + Date.now());

                    const audioHtml = data.user_audio_url ? `
                <audio id="audio-${uid}" src="${data.user_audio_url}" preload="none"></audio>
                <button type="button" class="btn btn-secondary btn-sm audio-btn"
                        onclick="toggleAudio('audio-${uid}', this)"
                        style="padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px;">
                    <svg class="play-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        <svg class="stop-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><rect x="6" y="6" width="12" height="12" rx="1" fill="currentColor"/></svg>
                    <span class="btn-text">Listen</span>
                </button>` : '';

                    const rewriteBtnHtml = `
                <button type="button" class="btn btn-outline btn-sm"
                        id="btn-rewrite-${uid}"
                        onclick="requestRewrite('${uid}', this, '{{ $debate->id }}')"
                        style="padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                    Improve argument
                </button>`;

                    const sttBadgeHtml = data.stt_provider === 'elevenlabs' ?
                        `<span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#34d399; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2);">Transcribed by ElevenLabs Scribe</span>` :
                        (data.stt_provider === 'gemini' ?
                            `<span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#818cf8; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);">Transcribed by Gemini Multimodal</span>` :
                            '');

                    const row = document.createElement('div');
                    row.className = 'turn-row user';
                    row.dataset.turnId = uid;
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    You ({{ ucfirst($debate->user_side) }})
                    ${sttBadgeHtml}
                </div>
                <div class="bubble user" style="${fontStyle}">${this.escapeHtml(data.user_transcript)}</div>
                <div class="turn-actions">${audioHtml}${rewriteBtnHtml}</div>
                <div id="rewrite-wrapper-${uid}" style="width:100%;">
                    <div id="rewrite-panel-${uid}" class="rewrite-panel"></div>
                </div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.turnCount++;
                    this.scrollFeed();
                },

                // Append AI turn with typewriter effect
                appendAiTurn(data, onFinish = null) {
                    this.removeStatusBubbles();
                    const container = document.getElementById('dynamic-turns');
                    const isBn = this.lang === 'bn';
                    const fontStyle = isBn ? "font-family:'Noto Sans Bengali',sans-serif;" : '';
                    const aid = 'ai-' + Date.now();

                    const moveTypeBadge = data.ai_move_type ?
                        `<span class="badge badge-accent" style="font-size:0.6rem; padding:0.08rem 0.3rem; text-transform:capitalize;">${data.ai_move_type.replace(/_/g,' ')}</span>` :
                        '';

                    const audioHtml = data.ai_audio_url ? `
                <audio id="audio-${aid}" src="${data.ai_audio_url}" preload="auto"></audio>` : '';

                    const row = document.createElement('div');
                    row.className = 'turn-row ai';
                    row.dataset.turnId = aid;
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
                    {{ $debate->persona->name }}
                    <span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#818cf8; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);">Logic: Gemini 3.6 Flash</span>
                    <span class="badge" style="font-size:0.6rem; padding:0.08rem 0.35rem; color:#818cf8; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);">Voice: ElevenLabs ({{ $debate->persona->voiceName() }})</span>
                    ${moveTypeBadge}
                </div>
                <div class="bubble ai ai-stream" style="${fontStyle}"></div>
                <div class="turn-actions">${audioHtml}</div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.turnCount++;

                    // Auto-play AI audio and sync typewriter
                    const audioEl = data.ai_audio_url ? document.getElementById(`audio-${aid}`) : null;
                    const bubble = row.querySelector('.ai-stream');
                    const actionsEl = row.querySelector('.turn-actions');

                    // Listen button — created only after the initial speech has played once
                    const createListenBtn = () => {
                        if (actionsEl.querySelector('.audio-btn')) return;
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-secondary btn-sm audio-btn';
                        btn.style.cssText = 'padding:0.25rem 0.6rem; font-size:0.7rem; gap:4px; display:inline-flex; align-items:center; animation:fadeUp 0.2s ease;';
                        btn.innerHTML = `<svg class="play-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        <svg class="stop-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><rect x="6" y="6" width="12" height="12" rx="1" fill="currentColor"/></svg>
                        <span class="btn-text">Listen</span>`;
                        btn.addEventListener('click', () => toggleAudio(`audio-${aid}`, btn));
                        actionsEl.appendChild(btn);
                    };

                    if (audioEl) {
                        let started = false;
                        const startPlayback = () => {
                            if (started) return;
                            started = true;
                            const dur = (isFinite(audioEl.duration) && audioEl.duration > 0) ? audioEl.duration : 10;
                            this.typewriterSynced(bubble, data.ai_text, dur, onFinish);

                            // Pause any other audio currently playing
                            document.querySelectorAll('audio').forEach(a => {
                                if (a !== audioEl && !a.paused) {
                                    a.pause();
                                    a.currentTime = 0;
                                    const obtn = a.nextElementSibling;
                                    if (obtn && obtn.classList.contains('audio-btn')) {
                                        const opi = obtn.querySelector('.play-icon');
                                        const osi = obtn.querySelector('.stop-icon');
                                        const otx = obtn.querySelector('.btn-text');
                                        if (opi) opi.style.display = 'inline-block';
                                        if (osi) osi.style.display = 'none';
                                        if (otx) otx.textContent = 'Listen';
                                    }
                                }
                            });

                            audioEl.play()
                                .then(() => {
                                    // Initial speech done → now allow relistening
                                    audioEl.onended = createListenBtn;
                                })
                                .catch(() => {
                                    // Autoplay blocked (no prior user gesture) → let the user play it manually
                                    createListenBtn();
                                });
                        };

                        // Multiple readiness strategies + instant error fallback + timeout
                        if (audioEl.readyState >= 1) {
                            startPlayback();
                        } else {
                            audioEl.addEventListener('loadedmetadata', startPlayback, { once: true });
                            audioEl.addEventListener('canplay', startPlayback, { once: true });
                            audioEl.addEventListener('error', startPlayback, { once: true });
                        }
                        setTimeout(() => { if (!started) startPlayback(); }, 1500);
                    } else {
                        // No ElevenLabs TTS audio — the text still streams; no speech, no Listen button
                        this.typewriterSynced(bubble, data.ai_text, Math.max(3, data.ai_text.length * 0.05), onFinish);
                    }

                    this.scrollFeed();

                    // Phase divider if new phase
                    if (data.round_complete && !data.debate_complete && data.new_phase) {
                        const phaseNames = @json($phaseLabels);
                        const div = document.createElement('div');
                        div.className = 'phase-divider';
                        div.innerHTML =
                            `<span>${phaseNames[data.new_phase] || data.new_phase}</span>`;
                        container.appendChild(div);
                    }
                },

                typewriterSynced(el, text, durationSeconds, onFinish = null) {
                    if (!el || !text) return;
                    if (el.dataset.started) return;
                    el.dataset.started = 'true';

                    // Calculate interval ms per character so typing finishes exactly when audio finishes
                    const totalMs = Math.max(2000, (durationSeconds || 5) * 1000);
                    const interval = Math.max(15, Math.floor(totalMs / text.length));

                    let i = 0;
                    const iv = setInterval(() => {
                        i++;
                        el.textContent = text.slice(0, i);
                        if (i % 15 === 0) this.scrollFeed();
                        if (i >= text.length) {
                            clearInterval(iv);
                            this.scrollFeed();
                            delete el.dataset.started;
                            if (typeof onFinish === 'function') onFinish();
                        }
                    }, interval);
                },

                escapeHtml(s) {
                    return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                        '&quot;');
                }
            };
        }

        /* ─────────────────────────────────────────────────────────
           PODIUM COMPONENT — handles mic, recording, submission
           ───────────────────────────────────────────────────────── */
        function podium(cfg) {
            return {
                // state: 'idle' | 'recording' | 'transcribing' | 'ai_thinking'
                state: cfg.nextSpeaker === 'user' ? 'idle' : 'ai_thinking',
                showRetry: false,
                errorMsg: '',
                aiThinkingMsg: `${cfg.personaName} is preparing their opening…`,
                timeLeft: cfg.phaseDuration,
                timerHandle: null,
                mediaRecorder: null,
                audioChunks: [],

                init() {
                    window.podiumComponent = this;
                    if (cfg.nextSpeaker !== 'user') {
                        // AI goes first — trigger automatically
                        this.triggerAiFirst();
                    }
                },

                dismissMessages() {
                    this.errorMsg = '';
                    this.showRetry = false;
                    if (this.state === 'idle' || this.state === 'transcribing' || this.state === 'ai_thinking') {
                        // Keep state; only clear messages
                    }
                },

                formatTime(s) {
                    const m = Math.floor(s / 60);
                    return `${String(m).padStart(2,'0')}:${String(s % 60).padStart(2,'0')}`;
                },

                async toggleRecording() {
                    if (this.state === 'recording') {
                        this.stopRecording();
                    } else if (this.state === 'idle') {
                        await this.startRecording();
                    }
                },

                async startRecording() {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({
                            audio: true
                        });
                        this.audioChunks = [];
                        this.mediaRecorder = new MediaRecorder(stream);
                        this.mediaRecorder.ondataavailable = e => {
                            if (e.data.size > 0) this.audioChunks.push(e.data);
                        };
                        this.mediaRecorder.onstop = () => {
                            stream.getTracks().forEach(t => t.stop());
                            const blob = new Blob(this.audioChunks, {
                                type: 'audio/webm'
                            });
                            this.handleBlob(blob);
                        };
                        this.mediaRecorder.start();
                        this.state = 'recording';
                        this.showRetry = false;
                        this.errorMsg = '';
                        this.startTimer();
                    } catch (err) {
                        console.error('Mic access denied:', err);
                        this.errorMsg = 'Microphone access denied. Please allow mic and try again.';
                    }
                },

                stopRecording() {
                    clearInterval(this.timerHandle);
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.state = 'transcribing';
                },

                startTimer() {
                    this.timeLeft = cfg.phaseDuration;
                    this.timerHandle = setInterval(() => {
                        this.timeLeft--;
                        if (this.timeLeft <= 0) this.stopRecording();
                    }, 1000);
                },

                async handleBlob(blob) {
                    if (blob.size < 2000) {
                        window.showToast("Couldn't capture your speech clearly. Please try speaking again.", "error");
                        this.state = 'idle';
                        return;
                    }

                    this.state = 'transcribing';
                    const localAudioUrl = URL.createObjectURL(blob);
                    window.debateArena?.showUserTranscribing(localAudioUrl);

                    const formData = new FormData();
                    formData.append('audio', blob, 'speech.webm');
                    formData.append('phase', cfg.currentPhase);
                    formData.append('_token', cfg.csrfToken);

                    try {
                        const res = await fetch(cfg.submitUrl, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();

                        if (data.error) {
                            // Server rejected the submission (e.g. round already complete)
                            window.debateArena?.removeStatusBubbles();
                            window.showToast(data.error, "error");
                            this.state = 'idle';
                            return;
                        }

                        // ── USER TURN: render regardless of AI result ──────────────────
                        if (data.user_transcript) {
                            // Transcription succeeded — show real transcript
                            window.debateArena?.finalizeUserTurn(data);
                        } else if (data.user_audio_url) {
                            // Transcription failed but audio was saved — show audio-only bubble
                            window.debateArena?.finalizeUserTurn({
                                ...data,
                                user_transcript: data.stt_error ?
                                    '[Speech could not be transcribed — audio is saved]' :
                                    '[No speech detected]',
                            });
                            if (data.stt_error) {
                                // Show inline STT error note on the user bubble (non-blocking)
                                window.debateArena?.showSttError(data.stt_error);
                            }
                        } else {
                            // Nothing at all (very bad network/server error)
                            window.debateArena?.removeStatusBubbles();
                            window.showToast("Speech capture failed. Please try again.", "error");
                            this.state = 'idle';
                            return;
                        }

                        // ── AI TURN: render independently of STT result ────────────────
                        if (data.ai_text) {
                            // AI succeeded — show thinking bubble then stream response
                            this.state = 'ai_thinking';
                            this.aiThinkingMsg = `${cfg.personaName} is crafting a response…`;
                            window.debateArena?.showAiThinking(this.aiThinkingMsg);

                            window.debateArena?.appendAiTurn(data, () => {
                                // Reload only when the debate is complete or when advancing phase AND user speaks first next
                                if (data.debate_complete) {
                                    setTimeout(() => location.reload(), 1500);
                                } else if (data.round_complete && data.new_phase) {
                                    if (data.next_speaker === 'user') {
                                        setTimeout(() => location.reload(), 1500);
                                    } else {
                                        // AI speaks first in the new phase — trigger directly without page reload
                                        this.state = 'ai_thinking';
                                        this.aiThinkingMsg = `${cfg.personaName} is preparing their ${data.new_phase} speech…`;
                                        window.debateArena?.showAiThinking(this.aiThinkingMsg);
                                        cfg.currentPhase = data.new_phase;
                                        this.triggerAiFirst();
                                    }
                                } else {
                                    this.state = 'idle';
                                    this.timeLeft = cfg.phaseDuration;
                                }
                            });
                        } else if (data.round_complete && (data.new_phase || data.debate_complete)) {
                            // User's turn completed the round.
                            window.debateArena?.removeStatusBubbles();
                            if (data.debate_complete) {
                                setTimeout(() => location.reload(), 1200);
                            } else if (data.next_speaker === 'user') {
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                // AI speaks first in the new phase (e.g. Closing phase reply reversal)
                                this.state = 'ai_thinking';
                                this.aiThinkingMsg = `${cfg.personaName} is preparing their ${data.new_phase} speech…`;
                                window.debateArena?.showAiThinking(this.aiThinkingMsg);
                                cfg.currentPhase = data.new_phase;
                                this.triggerAiFirst();
                            }
                        } else {
                            // AI failed — show AI-side error bubble with dedicated retry callback
                            window.debateArena?.showAiError(
                                data.ai_error || 'The opponent could not respond right now. Please try again.',
                                () => this.retryAiGeneration()
                            );
                            this.state = 'idle';
                        }

                    } catch (err) {
                        console.error('Submission failed:', err);
                        window.debateArena?.removeStatusBubbles();
                        window.showToast('Network error. Please check your connection and try again.', 'error');
                        this.state = 'idle';
                    }
                },

                async retryAiGeneration() {
                    this.state = 'ai_thinking';
                    this.aiThinkingMsg = `${cfg.personaName} is crafting a response…`;
                    window.debateArena?.showAiThinking(this.aiThinkingMsg);

                    const formData = new FormData();
                    formData.append('phase', cfg.currentPhase);
                    formData.append('ai_first', '1');
                    formData.append('_token', cfg.csrfToken);

                    try {
                        const res = await fetch(cfg.submitUrl, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();

                        if (data.ai_text) {
                            window.debateArena?.appendAiTurn(data, () => {
                                // Reload only when the server moved state forward
                                if (data.debate_complete || (data.round_complete && data.new_phase)) {
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    this.state = 'idle';
                                    this.timeLeft = cfg.phaseDuration;
                                }
                            });
                        } else {
                            window.debateArena?.showAiError(
                                data.ai_error || 'The opponent could not respond right now. Please try again.',
                                () => this.retryAiGeneration()
                            );
                            this.state = 'idle';
                        }
                    } catch (err) {
                        console.error('AI retry failed:', err);
                        window.debateArena?.showAiError('Network error trying to fetch response.', () => this
                            .retryAiGeneration());
                        this.state = 'idle';
                    }
                },

                async triggerAiFirst() {
                    this.state = 'ai_thinking';
                    const phaseLabel = cfg.currentPhase ? cfg.currentPhase.toLowerCase() : 'speech';
                    this.aiThinkingMsg = `${cfg.personaName} is preparing their ${phaseLabel}…`;
                    window.debateArena?.showAiThinking(this.aiThinkingMsg);

                    const formData = new FormData();
                    formData.append('phase', cfg.currentPhase);
                    formData.append('ai_first', '1');
                    formData.append('_token', cfg.csrfToken);

                    try {
                        const res = await fetch(cfg.submitUrl, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (!data.error) {
                            window.debateArena?.appendAiTurn(data, () => {
                                this.state = 'idle';
                                this.timeLeft = cfg.phaseDuration;
                            });
                        } else {
                            window.debateArena?.removeStatusBubbles();
                            this.errorMsg = data.message || 'Could not load opponent response.';
                            this.state = 'idle';
                        }
                    } catch (err) {
                        window.debateArena?.removeStatusBubbles();
                        this.errorMsg = 'Network error loading opponent response.';
                        this.state = 'idle';
                    }
                }
            };
        }

        /* ─────────────────────────────────────────────────────────
           REWRITE PANEL
           ───────────────────────────────────────────────────────── */
        async function requestRewrite(turnId, btn, debateId) {
            const panel = document.getElementById(`rewrite-panel-${turnId}`);
            if (!panel) return;

            btn.disabled = true;
            btn.textContent = 'Strengthening…';
            const esc = window.debateArena?.escapeHtml.bind(window.debateArena) || ((s) => s || '');

            try {
                const res = await fetch(`/debates/${debateId}/turns/${turnId}/rewrite`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                });
                const data = await res.json();

                if (!res.ok || data.error) {
                    btn.disabled = false;
                    btn.textContent = 'Improve argument';
                    panel.innerHTML =
                        `<p role="alert" style="font-size:0.78rem; color:var(--color-danger); padding:0.75rem 1rem; border:1px solid rgba(239,68,68,0.25); border-radius:var(--radius); background:rgba(239,68,68,0.05);">${esc(data.error || 'Could not improve your argument right now. Please try again.')}</p>`;
                    panel.classList.add('visible');
                    return;
                }

                const fontStyle = @json($fontStyle);
                const bulletsHtml = (data.explanation_bullets || []).map(b =>
                    `<li style="margin-bottom:0.2rem;">• ${esc(b)}</li>`).join('');

                panel.innerHTML = `
            <div style="padding:1rem; background:rgba(99,102,241,0.04); font-size:0.82rem; ${fontStyle}">
                <p style="font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--color-accent); margin-bottom:0.5rem;">Enhanced Version</p>
                <p style="line-height:1.6; color:#f0f4ff;">${esc(data.rewritten_text)}</p>
            </div>
            <div style="padding:0.65rem 1rem; border-top:1px solid rgba(99,102,241,0.2); background:rgba(99,102,241,0.03);">
                <p style="font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--color-text-muted); margin-bottom:0.35rem;">What changed</p>
                <ul style="font-size:0.78rem; color:var(--color-text-muted); list-style:none; ${fontStyle}">${bulletsHtml}</ul>
            </div>`;

                panel.classList.add('visible');
                if (btn) {
                    btn.style.setProperty('display', 'none', 'important');
                    btn.remove();
                }

            } catch (err) {
                btn.disabled = false;
                btn.textContent = 'Improve argument';
                console.error('Rewrite failed:', err);
            }
        }

        function toggleAudio(audioId, btn) {
            const audio = document.getElementById(audioId);
            if (!audio) return;

            const playIcon = btn.querySelector('.play-icon');
            const stopIcon = btn.querySelector('.stop-icon');
            const btnText  = btn.querySelector('.btn-text');
            if (btnText && !btn.dataset.origText) {
                btn.dataset.origText = btnText.textContent.trim();
            }
            const defaultLabel = btn.dataset.origText || 'Listen';

            if (!audio.paused) {
                audio.pause();
                audio.currentTime = 0;
                if (playIcon) playIcon.style.display = 'inline-block';
                if (stopIcon) stopIcon.style.display = 'none';
                if (btnText)  btnText.textContent = defaultLabel;
            } else {
                // Stop any other currently playing audio elements first
                document.querySelectorAll('audio').forEach(a => {
                    if (a.id !== audioId && !a.paused) {
                        a.pause();
                        a.currentTime = 0;
                        const parentBtn = a.nextElementSibling;
                        if (parentBtn && parentBtn.classList.contains('audio-btn')) {
                            const pIcon = parentBtn.querySelector('.play-icon');
                            const sIcon = parentBtn.querySelector('.stop-icon');
                            const txt   = parentBtn.querySelector('.btn-text');
                            if (pIcon) pIcon.style.display = 'inline-block';
                            if (sIcon) sIcon.style.display = 'none';
                            if (txt)   txt.textContent = parentBtn.dataset.origText || 'Listen';
                        }
                    }
                });

                if (audio.readyState === 0) {
                    audio.load();
                }

                audio.play().then(() => {
                    if (playIcon) playIcon.style.display = 'none';
                    if (stopIcon) stopIcon.style.display = 'inline-block';
                    if (btnText)  btnText.textContent = 'Stop';
                }).catch(err => {
                    console.warn('Audio playback prevented:', err);
                    if (window.showToast) {
                        window.showToast('Could not play audio. Check your audio device or try again.', 'error');
                    }
                });

                audio.onended = () => {
                    if (playIcon) playIcon.style.display = 'inline-block';
                    if (stopIcon) stopIcon.style.display = 'none';
                    if (btnText)  btnText.textContent = defaultLabel;
                };
            }
        }
    </script>

@endsection

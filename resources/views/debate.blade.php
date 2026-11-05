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

        /* ── Arena Shell ──
           The arena is a full-height workspace, not a scrolling page.
           It sits inside the shared shell: on a wide screen the rail
           is to its left, on a narrow one the shell's own bar is above
           it. The shell owns the space around it, so the arena takes
           the height that is left and manages its own two panes. */
        .arena-shell {
            display: flex;
            flex-direction: column;
            height: 100vh;
            /* dvh tracks the visible viewport, so a phone whose
               browser bars are showing does not push the last of the
               round below the fold. */
            height: 100dvh;
            overflow: hidden;
            background: var(--color-bg);
        }

        /* ── The mic dock (narrow screens only) ──
           The microphone is the one control the reader has to reach,
           and on a phone it has to be reachable *while reading* — you
           cannot speak an answer you cannot see the question for.

           So below the breakpoint the mic leaves the podium panel and
           docks to the foot of the screen, where a thumb rests. It is
           present on both panes, so switching between the transcript
           and the podium never costs you the ability to speak. */
        .arena-dock {
            display: none;
        }

        /* ── Panel switcher (narrow screens only) ──
           Below the breakpoint the transcript and the podium cannot
           both be on a phone screen, so one is put in front of the
           other. The switcher is a way of *reading* — of choosing
           what fills the screen — and not a way of reaching the mic,
           which is docked below and always present. */
        .arena-tabs {
            display: none;
            flex-shrink: 0;
            gap: 0.25rem;
            padding: 0.4rem 0.75rem;
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
        }

        .arena-tab {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            height: 34px;
            border: 1px solid transparent;
            border-radius: var(--radius);
            background: transparent;
            color: var(--color-text-muted);
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition:
                background-color 0.15s ease,
                border-color 0.15s ease,
                color 0.15s ease;
        }

        .arena-tab:hover {
            color: var(--color-text);
            background: var(--color-surface-2);
        }

        .arena-tab[aria-pressed="true"] {
            background: var(--color-surface-2);
            border-color: var(--color-border);
            color: var(--color-text);
        }

        /* A mark on the panel that has something waiting on it. It is a
           dot rather than a number because there is no count to show —
           the reader only needs to know that the other panel has moved
           on without them. */
        .arena-tab-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--color-accent-2);
            flex-shrink: 0;
        }

        /* ── The dock's contents ──
           The dock is a bar across the foot of the screen holding the
           round's state on the left and the microphone on the right,
           so the mic lands where a right thumb already is. */
        .arena-dock-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            /* The inner bar has to fill the dock — without this it
               shrinks to its content and the microphone ends up in the
               middle of the screen rather than at the edge of it. */
            flex: 1;
            min-width: 0;
            height: 100%;
        }

        /* The state and the clock, stacked on the left of the dock. */
        .arena-dock-left {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        /* The state of the round, stated in words. The status pill in
           the podium says the same thing, but on a phone the reader is
           often looking at the transcript, not the podium — so the
           dock repeats it where it can be seen. */
        .arena-dock-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 0;
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--color-text-muted);
        }

        .arena-dock-status > span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* The dot that pulses while the microphone is open. It is a
           shape as well as a colour, so the state is not carried by
           colour alone. */
        .arena-dock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--color-danger);
            flex-shrink: 0;
            animation: micPulse 1s infinite;
        }

        .arena-dock-dot.idle {
            background: var(--color-accent);
            animation: none;
        }

        .arena-dock-dot.waiting {
            background: var(--color-accent-2);
            animation: none;
        }

        /* The microphone in the dock is a wide, flat button rather
           than the round orb of the podium: on a phone the target has
           to be easy to hit without looking at it, and a full-width
           button under the thumb is. */
        .arena-dock-mic {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            height: 44px;
            padding: 0 1rem;
            flex-shrink: 0;
            border: 1px solid var(--color-accent);
            border-radius: var(--radius-lg);
            background: var(--color-accent);
            color: #fff;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition:
                background-color 0.15s ease,
                border-color 0.15s ease,
                opacity 0.15s ease;
        }

        .arena-dock-mic:hover {
            background: var(--color-accent-glow);
            border-color: var(--color-accent-2);
        }

        .arena-dock-mic.recording {
            background: var(--color-danger);
            border-color: var(--color-danger);
        }

        .arena-dock-mic:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* The clock, when there is one. It sits in the dock rather
           than in the podium so the reader can see it while reading
           the transcript — a time limit they cannot see is not a
           limit they can respect. */
        .arena-dock-timer {
            font-size: 0.95rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: var(--color-text);
            flex-shrink: 0;
        }

        .arena-dock-timer.warning {
            color: var(--color-danger);
        }

        .debate-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            /* The grid fills whatever the shell leaves, and its one row
               is allowed to shrink — otherwise the transcript's own
               scroll cannot take effect. */
            grid-template-rows: minmax(0, 1fr);
            flex: 1 1 auto;
            min-height: 0;
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
            /* Grid and flex children default to a minimum size of their
               content, which would stop the panes from shrinking and
               push a long speech into a page-wide scrollbar. */
            min-width: 0;
            min-height: 0;
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
            min-height: 0;
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
            min-width: 0;
            min-height: 0;
        }

        .podium-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-surface);
            flex-shrink: 0;
        }

        .podium-body {
            flex: 1;
            min-height: 0;
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

        /* ── Responsive ──
           The wide layout is two panes and stays that way for as long
           as it fits. Below 900px the podium would push the transcript
           off the bottom of the screen, so the two panes take turns
           instead: one is shown at a time and the reader switches
           between them from the panel bar.

           The microphone moves out of the podium and into a dock at the
           foot of the screen, so it is under the reader's thumb on
           either pane. A round that scrolls the whole page meant the
           mic was a full screen away from the transcript; a round that
           hides the mic behind a tab is worse, because the reader
           cannot speak an answer they cannot read the question for. */
        @media (max-width: 1024px) {
            /* The shell's own bar takes the top of the screen below
               this width, so the arena takes what is left. Without this
               the page grew a screen's worth of scrollbar and the
               round's last turn fell below the fold. */
            .arena-shell {
                height: calc(100dvh - 52px);
            }
        }

        @media (max-width: 900px) {
            .arena-tabs {
                display: flex;
            }

            /* The dock takes the foot of the arena. */
            .arena-dock {
                display: flex;
                align-items: center;
                flex-shrink: 0;
                height: 60px;
                padding: 0 0.85rem;
                /* The bar sits at the very bottom of the viewport, so
                   it needs the safe area a phone's home indicator
                   claims. */
                padding-bottom: env(safe-area-inset-bottom, 0px);
                height: calc(60px + env(safe-area-inset-bottom, 0px));
                background: var(--color-surface);
                border-top: 1px solid var(--color-border);
            }

            /* The podium's own microphone is the desktop one. On a
               phone the dock holds it instead, so there must not be
               two microphones on screen at once — two buttons that do
               the same thing are one button too many.

               The status pill and the clock go with it, so that the
               dock is the one place on a phone that says what the
               round is doing. Two clocks disagreeing about how long
               is left is worse than one. */
            .podium-col .podium-mic,
            .podium-col .podium-status,
            .podium-col .podium-timer {
                display: none !important;
            }

            .debate-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            /* Only the chosen pane is mounted in the layout. */
            .debate-layout[data-pane="transcript"] .podium-col,
            .debate-layout[data-pane="podium"] .transcript-col {
                display: none;
            }

            .transcript-col {
                border-right: none;
            }

            .transcript-header {
                padding: 0.9rem 1rem;
            }

            .transcript-feed {
                padding: 1rem 1rem 1.5rem;
                gap: 1.1rem;
            }

            .podium-header {
                padding: 0.9rem 1rem;
            }

            .podium-body {
                padding: 1rem;
                gap: 1.25rem;
            }

            /* A bubble that is 80% of a phone screen leaves no room to
               read the other side of the exchange. */
            .bubble {
                max-width: 88%;
                padding: 0.85rem 1rem;
                font-size: 0.88rem;
                line-height: 1.6;
            }

            /* The badges under each speaker name are a long string of
               provider credits; on a narrow column they wrapped onto
               three lines and pushed the speech down. */
            .turn-meta {
                flex-wrap: wrap;
                row-gap: 0.25rem;
            }

            .timeline-container {
                padding: 0.5rem 0.6rem;
            }

            .timeline-step {
                font-size: 0.66rem;
            }

            .mic-orb {
                width: 60px;
                height: 60px;
            }
        }

        /* ── Narrow phones ──
           At 380px and below the shell's bar, the panel bar and the
           phase header cannot all keep their padding. The type scales
           down a step rather than being clipped. */
        @media (max-width: 420px) {
            .transcript-header {
                padding: 0.75rem 0.85rem;
            }

            .podium-body {
                padding: 0.85rem;
            }

            .bubble {
                max-width: 94%;
                padding: 0.75rem 0.85rem;
            }

            .timer-display {
                font-size: 1.9rem;
            }

            /* The dock keeps the microphone big enough to hit without
               looking at it. The label stays — a button that says
               what it does is worth more than the few pixels it
               costs, and there is room for it on every phone but the
               narrowest. */
            .arena-dock {
                padding-inline: 0.7rem;
            }

            .arena-dock-mic {
                height: 42px;
                padding: 0 0.85rem;
            }
        }

        /* ── Short viewports ──
           A phone in landscape, or a laptop with the browser chrome
           open, leaves very little height. The arena gives its panes
           the space rather than the chrome, so the round stays usable
           without a page scroll. */
        @media (max-height: 560px) and (max-width: 900px) {
            .arena-tabs {
                padding: 0.3rem 0.5rem;
            }

            .arena-tab {
                height: 30px;
                font-size: 0.75rem;
            }

            /* The dock shrinks with the rest of the chrome, but the
               microphone does not shrink below a comfortable target —
               it is the one thing the reader must hit accurately. */
            .arena-dock {
                height: calc(52px + env(safe-area-inset-bottom, 0px));
            }

            .arena-dock-mic {
                height: 40px;
            }
        }

        /* ── The narrowest phones ──
           At 340px there is not room for the label beside the state
           and the microphone. The microphone keeps its icon — the
           state is still written out, so nothing is carried by the
           shape alone.

           It also keeps a 44px-wide target, which is the smallest a
           thumb can hit reliably. The icon alone is narrower than
           that, so the button is given a minimum width rather than
           being left as a slim strip at the edge of the screen. */
        @media (max-width: 340px) {
            .arena-dock-mic .arena-dock-mic-label {
                display: none;
            }

            .arena-dock-mic {
                padding: 0 0.75rem;
                min-width: 44px;
                justify-content: center;
            }
        }

        /* ── Print ──
           A printed round is the transcript, in order, on the page.
           The controls and the panels that manage it have no meaning
           on paper — and neither does the rail, which the arena now
           shares with every other page. */
        @media print {
            .arena-shell {
                height: auto;
                overflow: visible;
            }

            .arena-tabs,
            .arena-dock,
            .podium-col,
            .app-side,
            .app-side-bar,
            .app-side-scrim {
                display: none !important;
            }

            /* The shell pushes the content across to clear the rail.
               On paper the rail is gone, so the transcript would be
               printed with a hand's width of blank margin beside it. */
            body.has-side #main-content {
                padding-left: 0;
            }

            .debate-layout {
                display: block;
                overflow: visible;
            }

            .transcript-col {
                display: block;
                overflow: visible;
                border-right: none;
            }

            .transcript-feed {
                overflow: visible;
                max-height: none;
                padding: 0;
            }
        }
    </style>

    <div x-data="debateArena()" class="arena-shell" @keydown.window="onGlobalKey($event)">

        {{-- ── Panel switcher ──
             Below 900px the transcript and the podium cannot both be on
             a phone screen, so the switcher puts one in front of the
             other.

             It is a way of *reading*, not a way of speaking: the
             microphone is docked at the foot of the screen and stays
             there whichever panel is in front, so switching never
             costs the reader the ability to answer.

             The buttons are pressed-state toggles rather than tabs,
             because the panels are not separate pages — they are two
             views of the same round. --}}
        <div class="arena-tabs" role="group" aria-label="Arena panels">
            <button type="button"
                    class="arena-tab"
                    @click="showPane('transcript')"
                    :aria-pressed="pane === 'transcript'"
                    aria-controls="transcript-pane">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Transcript
                <span x-show="paneBadge === 'transcript'" x-cloak class="arena-tab-dot" aria-hidden="true"></span>
            </button>
            <button type="button"
                    class="arena-tab"
                    @click="showPane('podium')"
                    :aria-pressed="pane === 'podium'"
                    aria-controls="podium-pane">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" x2="12" y1="19" y2="22"/>
                </svg>
                Podium
                <span x-show="paneBadge === 'podium'" x-cloak class="arena-tab-dot" aria-hidden="true"></span>
            </button>
        </div>

        <div class="debate-layout" :data-pane="pane" id="debate-layout">

        {{-- ═══════════════════════════════════════════
         LEFT: TRANSCRIPT COLUMN
         ═══════════════════════════════════════════ --}}
        <div class="transcript-col" id="transcript-pane">

            {{-- Top header: motion + badges + timeline --}}
            <div class="transcript-header">
                <div style="display:flex; align-items:flex-start; gap:0.75rem;">
                    <span
                        style="font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; padding:0.2rem 0.55rem; background:var(--color-accent); color:#fff; border-radius:var(--radius-sm); flex-shrink:0; margin-top:3px;">MOTION</span>
                    {{-- The motion is what this page is about, so it is the
                         page's heading. It was a paragraph before, which
                         left the arena as the only page in the product
                         with no heading at all — a screen reader
                         announcing "main" with nothing to say what was
                         in it. --}}
                    <h1
                        style="{{ $fontStyle }} font-size:0.95rem; font-weight:600; line-height:1.5; color:var(--color-text); flex:1; margin:0; letter-spacing:-0.01em;">
                        {{ $motionText }}
                    </h1>
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
                                    {{-- The stored value is a bare file name; the URL is
                                         rebuilt here so the audio is served through
                                         the ownership-checked controller. --}}
                                    <audio id="audio-{{ $turn->id }}" src="{{ route('debates.audio', ['debate' => $debate->id, 'file' => basename($turn->audio_path)]) }}"
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
        <div class="podium-col" id="podium-pane">

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
    // The time budget of every phase, so the timer can reset to the
    // right length when the round moves on. Without this the clock
    // carries the previous phase's budget into the next one — a
    // three-minute opening clock on a two-minute rebuttal.
    'phaseDurations' => config('debate.phase_duration', []),
    'lang' => $lang,
    'isBn' => $isBn,
    'csrfToken' => csrf_token(),
    'submitUrl' => route('debates.turns.submit', $debate->id),
    'nextSpeaker' => $nextSpeaker,
    'personaName' => $debate->persona->name,
    'userSide' => $debate->user_side,
    'aiSide' => $aiSide,
]))"
                        style="display:flex; flex-direction:column; gap:1rem;">

                        {{-- Status pill --}}
                        <div class="podium-status" style="display:flex; justify-content:center;">
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
                        <div x-show="state === 'recording'" x-cloak class="podium-timer" style="text-align:center;">
                            <div class="timer-display" :class="timeLeft <= 30 ? 'warning' : ''"
                                x-text="formatTime(timeLeft)" aria-hidden="true"></div>
                            <p style="font-size:0.68rem; color:var(--color-text-dim); margin-top:0.25rem;"
                                x-text="timeLeft <= 30 ? 'Under 30 seconds — wrap up your point' : 'Time remaining'"></p>
                        </div>

                        {{-- Mic orb (shows when it's user's turn or recording) --}}
                        <div x-show="state === 'idle' || state === 'recording'" x-cloak
                            class="podium-mic"
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
                    <div class="info-row"><span>Difficulty</span><span style="text-transform:capitalize;">{{ str_replace('_', ' ', $debate->difficulty) }}</span></div>
                    <div class="info-row"><span>Format</span><span style="text-transform:capitalize;">{{ $debate->mode }}</span></div>
                    <div class="info-row"><span>Language</span><span style="text-transform:uppercase;">{{ $debate->language }}</span></div>
                </div>

                {{-- The stack behind the round.
                     Every speech used to carry the machinery that produced
                     it — which transcriber heard the reader, which model
                     reasoned, which voice spoke. Those badges crowded the
                     conversation and pushed the speakers' names out of
                     line with the speech itself.

                     The attribution still belongs somewhere; it just does
                     not belong beside every line of an argument. It
                     lives here, once, where the reader can look up from
                     the transcript and see what they are talking to. --}}
                <div style="margin-top:1rem; padding:0.65rem 0.75rem; background:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.18); border-radius:var(--radius-lg, 4px); font-size:0.68rem; color:#7b91b3; display:flex; flex-direction:column; gap:3px;">
                    <div style="display:flex; align-items:center; gap:5px; font-weight:700; color:#818cf8; letter-spacing:0.04em; text-transform:uppercase; margin-bottom:2px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                        AI Stack
                    </div>
                    <div>Logic: <strong>Gemini 3.6 Flash</strong></div>
                    <div>Transcribe: <strong>ElevenLabs Scribe</strong></div>
                    <div>Voice: <strong>ElevenLabs ({{ $debate->persona->voiceName($debate->language) }})</strong></div>
                </div>

            </div>
        </div>

        </div>

        {{-- ── The mic dock ──
             On a phone the microphone leaves the podium panel and
             docks to the foot of the screen, where a thumb rests.

             It is here, and not behind the panel switcher, because a
             reader answers an argument they can see. Making them
             leave the transcript to reach the microphone meant they
             could not read the question and speak the answer at the
             same time — and the podium's own copy is hidden below
             this width so there is only ever one microphone.

             The dock repeats the round's state and clock, because on
             a phone the reader is usually looking at the transcript
             rather than the podium, and a state they cannot see is a
             state they cannot act on.

             A finished round has nothing to record, so it has no
             dock — the podium is showing the adjudication link
             instead. --}}
        @unless ($isComplete)
        <div class="arena-dock" role="group" aria-label="Round controls">
            <div class="arena-dock-inner">

                <div class="arena-dock-left">
                    {{-- The state of the round, in words. The dot
                         carries the same information as a shape, so
                         the state is not signalled by colour alone. --}}
                    <span class="arena-dock-status" role="status" aria-live="polite">
                        <span class="arena-dock-dot" :class="{
                            'idle': podiumState === 'idle',
                            'waiting': podiumState === 'ai_thinking' || podiumState === 'transcribing',
                        }" aria-hidden="true"></span>
                        <span x-text="podiumState === 'idle' ? 'Your turn' :
                                     podiumState === 'recording' ? 'Recording' :
                                     podiumState === 'transcribing' ? 'Transcribing…' :
                                     podiumState === 'ai_thinking' ? 'Opponent responding…' :
                                     ''"></span>
                    </span>

                    {{-- The clock. Shown while recording, because a
                         limit the reader cannot see is a limit they
                         cannot respect. --}}
                    <span class="arena-dock-timer" x-show="podiumState === 'recording'" x-cloak
                        :class="{ 'warning': podiumTimeLeft <= 30 }"
                        x-text="formatClock(podiumTimeLeft)" aria-hidden="true"></span>
                </div>

                {{-- The microphone. It is the one control the reader
                     has to reach, so it is a wide target at the edge
                     of the screen where a thumb already is. --}}
                <button type="button"
                        class="arena-dock-mic"
                        :class="{ 'recording': podiumState === 'recording' }"
                        :aria-label="podiumState === 'recording' ? 'Stop recording' : 'Start recording (press Space)'"
                        :aria-pressed="podiumState === 'recording'"
                        @click="window.podiumComponent?.toggleRecording()"
                        :disabled="podiumState !== 'idle' && podiumState !== 'recording'">
                    <svg x-show="podiumState !== 'recording'" width="16" height="16"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                        <line x1="12" x2="12" y1="19" y2="22" />
                    </svg>
                    <svg x-show="podiumState === 'recording'" x-cloak width="16" height="16"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor" />
                    </svg>
                    <span class="arena-dock-mic-label"
                        x-text="podiumState === 'recording' ? 'Stop' : 'Speak'"></span>
                </button>

            </div>
        </div>
        @endunless
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

                // Which pane is in front on a narrow screen. The wide
                // layout shows both at once, so this only matters below
                // the breakpoint — but it is kept in state either way so
                // the choice survives a resize.
                pane: 'transcript',

                // The panel the round is waiting on: the podium when it
                // is your move, the transcript when the opponent is
                // answering or your speech is being transcribed.
                pendingPane: null,

                // A mirror of the podium's state, kept here so the dock
                // — which lives outside the podium's Alpine scope — can
                // show what the round is doing.
                //
                // The podium writes to this on every state change; the
                // dock reads it. It is the same shape as pendingPane
                // for the same reason: one place that knows what the
                // round is doing, rather than two that have to agree.
                podiumState: 'idle',

                // The clock, mirrored the same way. A time limit the
                // reader cannot see is not a limit they can respect,
                // and on a phone they are usually looking at the
                // transcript rather than the podium.
                podiumTimeLeft: {{ $phaseDuration }},

                /**
                 * The panel to mark as having something waiting on it.
                 *
                 * When the reader is looking at one panel while the
                 * round waits on the other, the switcher says so rather
                 * than leaving them to find out.
                 */
                get paneBadge() {
                    if (!this.pendingPane) return null;
                    return this.pendingPane === this.pane ? null : this.pendingPane;
                },

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

                /**
                 * Show one of the two panels.
                 *
                 * On a wide viewport both panels are visible at once and
                 * this changes nothing — the switcher is hidden there.
                 * On a narrow one it swaps them, and the transcript is
                 * scrolled to the newest turn so the reader lands on
                 * what was just said rather than where they left off.
                 */
                showPane(which) {
                    this.pane = which;

                    if (which === 'transcript') {
                        this.$nextTick(() => this.scrollFeed());
                    }
                },

                /**
                 * Called when the round's attention moves to a panel.
                 *
                 * The switcher no longer moves on its own. It used to
                 * follow the turn, which made sense when the mic lived
                 * inside the podium — but then reaching the microphone
                 * meant switching panels, and a reader could not speak
                 * an answer they could not read the question for.
                 *
                 * Now the mic is docked below and always present, so
                 * the switcher is only a choice of what to read, and a
                 * choice the reader should keep. The round's state is
                 * reported by the dock instead, which sits where it
                 * cannot be missed.
                 *
                 * The dot on the other button still marks where the
                 * round is waiting.
                 */
                setPendingPane(where) {
                    this.pendingPane = where;
                },

                /**
                 * Mirror the podium's state and clock into the arena.
                 *
                 * The dock is outside the podium's Alpine scope, so it
                 * cannot read the podium's own properties. The podium
                 * calls this whenever either changes, and the dock
                 * renders from here.
                 */
                syncPodium(state, timeLeft) {
                    this.podiumState = state;
                    this.podiumTimeLeft = timeLeft;
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

                /**
                 * Format a number of seconds as a clock.
                 *
                 * The podium has its own copy of this for its timer;
                 * the dock needs one too, and the dock lives outside the
                 * podium's scope. Two copies of a one-line formatter is
                 * a smaller cost than moving the timer into a scope
                 * that would have to wrap both panes.
                 */
                formatClock(s) {
                    const total = Math.max(0, Math.floor(s || 0));
                    const m = Math.floor(total / 60);
                    const sec = total % 60;
                    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
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

                    const row = document.createElement('div');
                    row.className = 'turn-row user';
                    row.dataset.turnId = uid;
                    row.innerHTML = `
                <div class="turn-meta">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    You ({{ ucfirst($debate->user_side) }})
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

                // Append the AI turn
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
                    ${moveTypeBadge}
                </div>
                <div class="bubble ai ai-speech" style="${fontStyle}"></div>
                <div class="turn-actions">${audioHtml}</div>`;
                    this.pinned = true;
                    container.appendChild(row);
                    this.turnCount++;

                    // Auto-play the AI audio. The speech itself is shown in
                    // full the moment it arrives — it is not typed out.
                    const audioEl = data.ai_audio_url ? document.getElementById(`audio-${aid}`) : null;
                    const bubble = row.querySelector('.ai-speech');
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

                    // The speech is shown in full, straight away. The audio
                    // then plays alongside the text the reader already has.
                    this.renderSpeech(bubble, data.ai_text);

                    // The turn is only over once the opponent has finished
                    // speaking. The microphone stays locked until then,
                    // otherwise the reader records over the top of the AI's
                    // voice and the transcription hears both.
                    let turnFinished = false;
                    const finishTurn = () => {
                        if (turnFinished) return;
                        turnFinished = true;
                        if (typeof onFinish === 'function') onFinish();
                    };

                    if (audioEl) {
                        let started = false;
                        const startPlayback = () => {
                            if (started) return;
                            started = true;

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

                            // The turn ends when the speech ends.
                            audioEl.onended = () => {
                                createListenBtn();
                                finishTurn();
                            };

                            audioEl.play()
                                .catch(() => {
                                    // Autoplay blocked (no prior user gesture).
                                    // The reader can still play it manually
                                    // from the Listen button — but the turn
                                    // cannot wait on audio that will never
                                    // start, so the microphone is released.
                                    createListenBtn();
                                    finishTurn();
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
                        // No audio to wait for — the speech is the whole turn.
                        finishTurn();
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

                /**
                 * Put the AI's speech into the bubble in one go.
                 *
                 * The text used to type itself out a character at a time,
                 * timed to finish as the audio did. That made the reader
                 * wait through the whole speech before they could read it,
                 * and it meant the words on screen were not the words they
                 * could have read a second earlier. The speech arrives
                 * complete, so it is shown complete.
                 *
                 * @param {HTMLElement} el   The bubble to fill
                 * @param {string}      text The full speech
                 */
                renderSpeech(el, text) {
                    if (!el || !text) return;

                    // textContent, not innerHTML — the speech is the
                    // model's own words and must never be read as markup.
                    el.textContent = text;
                    this.scrollFeed();
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

                    // The dock mirrors the round's state.
                    //
                    // The dock sits outside this scope — it has to, so
                    // that it can hold the microphone on whichever pane
                    // is in front — so it cannot read these properties
                    // directly. It reads them from the arena instead,
                    // which is the one scope both of them can see.
                    //
                    // The watcher is the one place that knows, because
                    // every path through the round ends in a state
                    // change.
                    this.$watch('state', (state) => {
                        const arena = window.debateArena;
                        if (!arena) return;

                        // The dock's own copy of the state.
                        arena.syncPodium(state, this.timeLeft);

                        // Which panel the round is waiting on. The
                        // switcher marks it, but does not move to it —
                        // the reader's choice of what to read is their
                        // own, and the microphone is always at hand.
                        if (state === 'idle' || state === 'recording') {
                            arena.setPendingPane('podium');
                        } else {
                            arena.setPendingPane('transcript');
                        }
                    });

                    // The clock changes every second while recording,
                    // so it is mirrored on its own watch rather than
                    // folded into the state watch above.
                    this.$watch('timeLeft', (seconds) => {
                        window.debateArena?.syncPodium(this.state, seconds);
                    });

                    // The arena is the outer scope, so it is already
                    // initialised by the time this runs — but the
                    // first mirror is written here anyway, so the dock
                    // is correct before any state change happens.
                    window.debateArena?.syncPodium(this.state, this.timeLeft);

                    if (cfg.nextSpeaker !== 'user') {
                        // AI goes first — trigger automatically
                        this.triggerAiFirst();
                    } else {
                        // Your move from the start.
                        window.debateArena?.setPendingPane('podium');
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
                    this.timeLeft = this.durationFor(cfg.currentPhase);
                    this.timerHandle = setInterval(() => {
                        this.timeLeft--;
                        if (this.timeLeft <= 0) this.stopRecording();
                    }, 1000);
                },

                // The time budget of a phase. The clock has to know the
                // phase it is timing, not just the phase it started in:
                // the opening is three minutes and the rebuttal is two,
                // so a clock that carries its budget across a phase
                // change gives the speaker the wrong amount of time.
                durationFor(phase) {
                    const durations = cfg.phaseDurations || {};
                    return durations[phase] ?? cfg.phaseDuration;
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
                            // Ask for JSON so a validation failure comes back as
                            // a 422 JSON body instead of an HTML redirect page
                            // (which would make res.json() throw).
                            headers: { 'Accept': 'application/json' },
                            body: formData
                        });
                        const data = await res.json();

                        if (data.error || data.errors) {
                            // Server rejected the submission — either a domain
                            // error ("round already complete") or a 422
                            // validation failure (data.errors is a field map).
                            window.debateArena?.removeStatusBubbles();
                            const message = data.error
                                || Object.values(data.errors || {}).flat()[0]
                                || "Could not submit your turn.";
                            window.showToast(message, "error");
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
                            // AI succeeded — show the thinking bubble, then the speech
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
                                    this.timeLeft = this.durationFor(cfg.currentPhase);
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
                            headers: { 'Accept': 'application/json' },
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
                                    this.timeLeft = this.durationFor(cfg.currentPhase);
                                }
                            });
                        } else {
                            window.debateArena?.showAiError(
                                data.ai_error
                                || (data.errors ? Object.values(data.errors).flat()[0] : null)
                                || 'The opponent could not respond right now. Please try again.',
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
                            headers: { 'Accept': 'application/json' },
                            body: formData
                        });
                        const data = await res.json();
                        if (!data.error && !data.errors) {
                            window.debateArena?.appendAiTurn(data, () => {
                                this.state = 'idle';
                                this.timeLeft = this.durationFor(cfg.currentPhase);
                            });
                        } else {
                            window.debateArena?.removeStatusBubbles();
                            this.errorMsg = data.message
                                || (data.errors ? Object.values(data.errors).flat()[0] : null)
                                || 'Could not load opponent response.';
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
                        'Accept': 'application/json',
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

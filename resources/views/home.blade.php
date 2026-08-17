@extends('layouts.app')

@section('title', 'Rostrum — AI Debate Training Platform')
@section('meta_description', 'Practice competitive debate against an AI opponent in English or Bangla — with expert adjudication feedback.')
@section('body_class', 'is-landing')

@section('nav_actions')
    <a href="{{ route('history.index') }}" class="btn btn-ghost btn-sm nav-history-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        History
    </a>
    <a href="{{ route('setup') }}" class="btn btn-primary btn-sm" id="nav-start-practice">
        Get Started
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
@endsection

@section('content')

<div class="landing">

    {{-- Ambient background --}}
    <div class="landing-bg" aria-hidden="true"></div>

    {{-- Hero --}}
    <section class="landing-hero landing-container">
        <div class="landing-hero-grid">

            <div class="landing-hero-copy">
                <div class="landing-reveal">
                    <span class="landing-eyebrow">AI Debate Training Platform</span>
                </div>

                <h1 class="landing-display landing-hero-title landing-reveal landing-reveal-delay-1">
                    Debate Sharper.<br>
                    <span class="landing-text-accent">Score Higher.</span>
                </h1>

                <p class="landing-hero-sub landing-reveal landing-reveal-delay-2">
                    Practice structured competitive debate against an AI opponent. Receive formal adjudication reports scored on Matter, Manner, and Method — the same criteria used in university championships.
                </p>

                <div class="landing-hero-actions landing-reveal landing-reveal-delay-3">
                    <a href="{{ route('setup') }}" id="btn-start-practice" class="landing-btn-primary">
                        Begin a Debate Session
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    <a href="#how-it-works" class="landing-btn-secondary">
                        See how it works
                    </a>
                </div>

                <div class="landing-stats landing-reveal landing-reveal-delay-4">
                    <div class="landing-stat">
                        <div class="landing-stat-value">3</div>
                        <div class="landing-stat-label">Debate Phases</div>
                    </div>
                    <div class="landing-stat">
                        <div class="landing-stat-value">3</div>
                        <div class="landing-stat-label">AI Personas</div>
                    </div>
                    <div class="landing-stat">
                        <div class="landing-stat-value">100</div>
                        <div class="landing-stat-label">Point Rubric</div>
                    </div>
                    <div class="landing-stat">
                        <div class="landing-stat-value">2</div>
                        <div class="landing-stat-label">Languages</div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Marquee --}}
    <div class="landing-marquee-wrap" aria-hidden="true">
        <div class="landing-marquee">
            <div class="landing-marquee-track">
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg> Structured 3-Phase Debates</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Real-Time Adjudication</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Fallacy Detection</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> English &amp; Bangla</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg> 100-Point Rubric</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> 3 AI Personas</span>
            </div>
            <div class="landing-marquee-track" aria-hidden="true">
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg> Structured 3-Phase Debates</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Real-Time Adjudication</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Fallacy Detection</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg> English &amp; Bangla</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg> 100-Point Rubric</span>
                <span class="landing-marquee-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> 3 AI Personas</span>
            </div>
        </div>
    </div>

    {{-- Features bento --}}
    <section class="landing-section landing-container">
        <div class="landing-section-head">
            <span class="landing-eyebrow">Platform Features</span>
            <h2 class="landing-display landing-section-title">
                Everything you need to <span class="landing-text-accent">train like a champion</span>
            </h2>
            <p class="landing-section-desc">
                From motion generation to formal adjudication — Rostrum mirrors the structure and rigor of competitive university debate.
            </p>
        </div>

        <div class="landing-bento">
            {{-- Card 1: Adaptive AI Opponents (span 7 on md+) --}}
            <div class="landing-bento-card landing-bento-card--seven">
                <div class="landing-bento-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3 class="landing-bento-title">3 AI Personas &amp; 4 Difficulty Levels</h3>
                <p class="landing-bento-desc">Face Calm Logician, Aggressive Cross-Examiner, or Devil's Advocate — each calibrated to difficulty tiers from Beginner to World Champion.</p>
                <div class="landing-bento-visual">
                    <div class="landing-persona-row">
                        <span class="landing-persona-chip">Calm Logician</span>
                        <span class="landing-persona-chip is-highlight">Aggressive Cross-Examiner</span>
                        <span class="landing-persona-chip">Devil's Advocate</span>
                    </div>
                </div>
            </div>

            {{-- Card 2: Argument Rewrite Assistant (span 5 on md+) --}}
            <div class="landing-bento-card landing-bento-card--five">
                <div class="landing-bento-icon landing-bento-icon--amber">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <h3 class="landing-bento-title">Live Argument Rewriting</h3>
                <p class="landing-bento-desc">Refine your speeches mid-debate with phase-aware Gemini rewrites that show live before/after comparisons and coaching tips.</p>
            </div>

            {{-- Card 3: Championship Adjudication (span 4 on md+) --}}
            <div class="landing-bento-card landing-bento-card--third">
                <div class="landing-bento-icon landing-bento-icon--sky">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <h3 class="landing-bento-title">Formal Adjudication</h3>
                <p class="landing-bento-desc">Receive 100-point rubric reports scored on Matter (/40), Manner (/30), and Method (/30) with logical fallacy detection.</p>
            </div>

            {{-- Card 4: English & Bangla Modes (span 4 on md+) --}}
            <div class="landing-bento-card landing-bento-card--third">
                <div class="landing-bento-icon landing-bento-icon--purple">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <h3 class="landing-bento-title">English &amp; Bangla Parity</h3>
                <p class="landing-bento-desc">Full end-to-end support for English and Bangla modes — motions, spoken opponent audio, rewrites, and feedback sheets.</p>
            </div>

            {{-- Card 5: Tournament & Sparring Formats (span 4 on md+) --}}
            <div class="landing-bento-card landing-bento-card--third">
                <div class="landing-bento-icon landing-bento-icon--green">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                </div>
                <h3 class="landing-bento-title">Tournament &amp; Sparring</h3>
                <p class="landing-bento-desc">Practice in structured 3-phase Tournament mode (Opening, Rebuttal, Closing) or conversational Sparring mode.</p>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="landing-section landing-container">
        <div class="landing-section-head">
            <span class="landing-eyebrow">How It Works</span>
            <h2 class="landing-display landing-section-title">
                Four steps to your next <span class="landing-text-accent">breakthrough</span>
            </h2>
            <p class="landing-section-desc">
                From motion to adjudication in a single focused session — no setup friction, no learning curve.
            </p>
        </div>

        <div class="landing-steps">
            <div class="landing-step">
                <div class="landing-step-num">01</div>
                <h3 class="landing-step-title">Select Motion</h3>
                <p class="landing-step-desc">Choose from curated debate motions or generate one by topic and domain.</p>
            </div>
            <div class="landing-step">
                <div class="landing-step-num">02</div>
                <h3 class="landing-step-title">Configure Opponent</h3>
                <p class="landing-step-desc">Pick an AI persona and difficulty level from Beginner to World Champion.</p>
            </div>
            <div class="landing-step">
                <div class="landing-step-num">03</div>
                <h3 class="landing-step-title">Deliver Speeches</h3>
                <p class="landing-step-desc">Speak through three structured rounds — Opening, Rebuttal, and Closing.</p>
            </div>
            <div class="landing-step">
                <div class="landing-step-num">04</div>
                <h3 class="landing-step-title">Read the Report</h3>
                <p class="landing-step-desc">Receive a formal adjudication report with scores, fallacy flags, and coaching notes.</p>
            </div>
        </div>
    </section>

    {{-- Scoring rubric --}}
    <section class="landing-section landing-container">
        <div class="landing-section-head">
            <span class="landing-eyebrow">Scoring Framework</span>
            <h2 class="landing-display landing-section-title">
                Judged on the <span class="landing-text-accent">MMM rubric</span>
            </h2>
            <p class="landing-section-desc">
                The same Matter, Manner, and Method criteria used in competitive university debate — scored out of 100 points.
            </p>
        </div>

        <div class="landing-rubric">
            <div class="landing-rubric-card">
                <div class="landing-rubric-points">40</div>
                <div class="landing-rubric-name">Matter</div>
                <p class="landing-rubric-desc">Strength of arguments, evidence quality, logical consistency, and rebuttal effectiveness.</p>
            </div>
            <div class="landing-rubric-card">
                <div class="landing-rubric-points">30</div>
                <div class="landing-rubric-name">Manner</div>
                <p class="landing-rubric-desc">Delivery, persuasion, rhetorical technique, and audience engagement throughout the debate.</p>
            </div>
            <div class="landing-rubric-card">
                <div class="landing-rubric-points">30</div>
                <div class="landing-rubric-name">Method</div>
                <p class="landing-rubric-desc">Structure, time management, role fulfillment, and strategic allocation across all three phases.</p>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="landing-container">
        <div class="landing-cta">
            <h2 class="landing-display landing-cta-title">
                Ready to take the <span class="landing-text-accent">podium</span>?
            </h2>
            <p class="landing-cta-desc">
                Start your first debate session in under a minute. No account required — just pick a motion and begin.
            </p>
            <a href="{{ route('setup') }}" class="landing-btn-primary">
                Start Practicing Now
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="landing-footer">
        <div class="landing-container landing-footer-inner">
            <div class="landing-footer-brand">
                <span class="nav-logo-mark"></span>
                Rostrum
            </div>
            <p class="landing-footer-copy">
                &copy; {{ date('Y') }} Rostrum · Powered by <strong>Gemini 3.6 Flash</strong> &amp; <strong>ElevenLabs Scribe &amp; Voice</strong>
            </p>
        </div>
    </footer>

</div>

@endsection

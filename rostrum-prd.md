# Product Requirements Document: Rostrum

**AI-Powered Debate Practice Simulator**
Build target: Build With AI Hack Days @EMK, Aug 17, 2026
Status: Draft for hackathon build

---

## 1. Overview

Rostrum is a web application that lets a student practice competitive debate against an AI opponent. The user picks or generates a motion, selects a side, an AI persona, and a difficulty level, then debates through a structured round format (Opening → Rebuttal → Closing) by speaking aloud. The AI opponent argues in character using Gemini and speaks back using a distinct ElevenLabs voice. After the round, Gemini acts as an adjudicator and produces a structured feedback sheet modeled on real debate judging (Matter / Manner / Method), including fallacy detection and a score. At any point during the round, the user can also ask Gemini to **rewrite one of their own arguments**, seeing a live before/after that demonstrates stronger reasoning — one of the product's strongest live-demo moments.

The product supports **English and Bangla** debate modes, end to end — motions, AI arguments, spoken voice, rewrite suggestions, and adjudication feedback.

The entire application is a single Laravel app: **Laravel Blade views styled with Tailwind CSS**, with light client-side interactivity (mic recording, timers, live transcript updates) handled by Alpine.js and small fetch calls back to the same Laravel app. There is no separate frontend framework or API domain.

---

## 2. Problem Statement & Vision

Debate is a major extracurricular activity at universities in Bangladesh (Asian Parliamentary format is the dominant competitive style), but practice partners, feedback, and judged sparring time are scarce outside scheduled club sessions. Rostrum gives any student an on-demand sparring partner, a coach, and a judge, available in their own language, at any hour.

**Vision:** the fastest way to get one full judged debate round of practice — plus a concrete example of how to argue better — solo, in under 15 minutes.

---

## 3. Goals & Non-Goals

### Goals (hackathon MVP)
- A user can generate or type a motion, pick a side, a persona, and a difficulty level, and complete a full 3-phase debate round entirely by voice.
- The AI opponent responds in-character, at the selected difficulty, in the correct language, with synthesized speech.
- At any point, the user can request a **rewrite suggestion** on one of their own arguments and see a clear before/after that visibly improves the reasoning — this is a first-class feature, not an afterthought, because it shows Gemini's reasoning quality directly rather than just a score.
- The system produces a structured, rubric-based feedback sheet at the end of the round.
- Full parity between English and Bangla for every user-facing step.

### Stretch Goal (design-in-now, build-if-time)
- **Sparring Mode**: an alternative, more conversational round format where the AI can ask follow-up questions, spot contradictions, and issue live challenges instead of trading fixed-length speeches. See §6.3a. This is architected as a `mode` selection from the start so it isn't a bolt-on later, but it is **not required for the hackathon submission** — Tournament Mode (the structured Opening/Rebuttal/Closing format) is the P0 default and must work end-to-end regardless of whether Sparring Mode ships.

### Non-Goals (explicitly out of scope for the hackathon build)
- Multiplayer / human-vs-human debate.
- Team debates (this is 1v1 solo practice, not full Asian Parliamentary 4-team format).
- Points of Information (POIs) / live interruptions during speeches — noted as a post-hackathon feature.
- Persistent user accounts, login, or cross-device history (session-based only for MVP).
- Mobile native apps (responsive web only, server-rendered).
- Leaderboards / social features.

---

## 4. Target Users

| Persona | Description | Primary Need |
|---|---|---|
| University debater (competitive) | Active member of a debate club, prepping for tournaments | Realistic rebuttal practice + rubric-accurate feedback + concrete examples of stronger phrasing |
| Debate-curious student | Never debated formally, curious to try | Low-friction onboarding, forgiving persona, Beginner difficulty, clear format explanation |
| Bangla-first speaker | More comfortable arguing in Bangla than English | Full-fidelity Bangla experience, not a translated afterthought |

---

## 5. Core User Journey

1. **Land on home screen** → short product pitch, stats (3 phases / 3 personas / 100-point rubric / 2 languages), "Begin a Debate Session" CTA.
2. **Set up the round**: choose **language** (English / Bangla — affects audio, transcription, and AI responses), enter a motion manually, or tap "Generate Motion" (Gemini generates one, optionally by topic/category).
3. **Choose side**: Government (Proposition) or Opposition — manual pick, or "Surprise me" (random assignment).
4. **Choose AI persona**: Calm Logician / Aggressive Cross-Examiner / Devil's Advocate — this controls *style*.
5. **Choose difficulty**: Beginner / Intermediate / Advanced / World Champion — this controls *skill level*, independent of persona (see §6.2a).
6. **Choose mode**: Tournament (structured, timed speeches — default) or Sparring (adaptive, conversational — see §6.3a). A standards note under the mode cards explains which debating format each mode follows (WSDC/AP for Tournament — including the Closing-order reversal — no fixed format for Sparring).
7. **Debate begins** — phase indicator shows Opening → Rebuttal → Closing (Tournament Mode), with a timer per speech.
8. Each phase: user taps mic, speaks, taps stop → sees their live transcript → optionally taps **"Improve my argument"** to see a Gemini rewrite of what they just said (§6.4a) → AI's turn plays automatically (text appears + voice plays).
9. After Closing phase completes both sides → **Adjudication screen** loads: scores, fallacies flagged, written feedback, verdict.
10. User can **download the transcript** as a `.txt` file, view the feedback sheet again, or **start a new round**. Past debates appear on the **History screen** (visible in the nav), where any session can be reopened or deleted.

---

## 6. Functional Requirements

### 6.1 Motion Management
- Manual text input (free text, either language).
- "Generate Motion" button → calls Gemini with an optional category (Politics, Technology, Society, Environment, Education, Economy) → returns a well-formed, debatable motion in the selected language.
- Motion is displayed persistently at the top of the debate screen throughout the round.

### 6.2 Persona Selection
- Card selection UI (radio-style grid), each card with the persona's name and a one-line description of its argumentative style.

| Persona | Argumentative style | Voice character |
|---|---|---|
| Calm Logician | Structured, evidence-led, low emotional register, methodically dismantles weak premises | Measured pace, neutral/warm tone |
| Aggressive Cross-Examiner | Direct, challenges assumptions hard, rapid-fire rebuttals, interrupts weak logic forcefully | Faster pace, assertive, higher energy |
| Devil's Advocate | Contrarian, deliberately picks the least comfortable counter-argument, wry/ironic framing | Slightly playful, sardonic inflection |

### 6.2a Difficulty Selection (Beginner → World Champion)
Persona controls *how* the AI argues (style/tone); difficulty controls *how well* it argues (skill level). The two are orthogonal — any persona can be played at any difficulty (e.g. a Beginner-difficulty Aggressive Cross-Examiner is still confrontational in tone, just easier to out-argue).

- Simple 4-level selector: **Beginner → Intermediate → Advanced → World Champion**.
- Implemented as a prompt parameter appended to the persona's `system_prompt` at call time — no separate model, no extra latency, no DB migration beyond a single enum column on `debates`.
- Difficulty tiers modulate:
  - Argument sophistication and use of evidence/examples.
  - How aggressively the AI exploits weak premises or contradictions in the user's speech.
  - Vocabulary and rhetorical complexity.
  - Fallback behavior: World Champion is instructed to actively look for the single weakest link in the user's case each turn; Beginner is instructed to argue simply and let obvious weaknesses in the user's case go unchallenged.
- Difficulty is selected once per round alongside persona and does not change mid-round.

### 6.3 Debate Round Engine — Tournament Mode (default, Asian-Parliamentary-inspired, 1v1 adaptation)
Three phases per round, each phase = one speech from the user and one from the AI:

| Phase | Who speaks first | Suggested time limit |
|---|---|---|
| Opening | Whoever is Government/Proposition (user or AI) | 3 min |
| Rebuttal | Same speaking order as Opening | 2 min |
| Closing | **Reversed** — whoever spoke second in Opening speaks first | 2 min |

*Design note: standard Asian Parliamentary format uses 4 speakers per side plus a Reply speech. This is deliberately adapted to a 1v1 solo-practice structure — this adaptation should be called out explicitly in the Devpost submission. The Closing order reversal matches real AP/reply-speech convention: whichever side opened the round does not also get the last word in the close — this needs to be an explicit rule in the round-engine logic, not just a UI label, since it changes who the debate screen prompts to speak first in that phase.*

- Countdown timer per speech, visually flashes at 30s remaining.
- User can end their speech early; cannot exceed the time limit (mic auto-stops at limit).
- AI response is generated with awareness of full prior transcript (motion, side, persona, difficulty, all previous turns in the round) so rebuttals genuinely engage with what the user said.

### 6.3a Debate Round Engine — Sparring Mode (stretch, alternative to Tournament Mode)
Selected instead of Tournament Mode at setup. Trades the fixed timed-speech structure for a live, back-and-forth exchange:

- No fixed Opening/Rebuttal/Closing phases with hard time boxes. Instead, the user and AI trade shorter turns.
- After each user turn, the AI can respond with one of several move types instead of always giving a full counter-speech:
  - A **follow-up question** probing an unsupported claim.
  - A **contradiction callout** if the user's current turn conflicts with something said earlier in the round.
  - A **live challenge** ("give me one piece of evidence for that").
  - A standard rebuttal, when none of the above apply.
- Demos better ("the AI is thinking on its feet"), but sacrifices the tight, tournament-style structure and predictable pacing that Tournament Mode gives judges/spectators to follow along with.
- The `debates.mode` column and the AI-turn prompt already branch on mode from the start of the build (see §10, §12.1), so Sparring Mode is a same-shaped extension of the turn loop rather than a parallel system — but given the 6-hour build window, it is explicitly P1/P2 (see §18) behind a working Tournament Mode.
- Adjudication (§6.7) works the same way regardless of mode — it scores the full transcript either way.

### 6.4 Voice Input
- Browser `MediaRecorder`, invoked from an Alpine.js component on the Blade debate page, captures the user's speech as an audio blob per turn.
- Audio is sent via `fetch` to a same-origin Laravel route, which forwards it to ElevenLabs Scribe (`scribe_v1`) for transcription first; if Scribe fails or returns near-empty text, the same audio is sent directly to **Gemini multimodal** (`generateDebateTurnFromAudio`), which transcribes **and** generates the AI's response in a single call.
- **Silent, near-empty, or mic-failure recordings are an explicit case, not an unhandled edge:**
  - Client-side: if no audio was recorded (or the mic/`MediaRecorder` failed), Alpine.js shows an inline "we didn't catch that — try again" state and does not submit a blob, so no wasted Gemini call.
  - Server-side: the user's audio file is **always saved** to local storage even when transcription fails. If both Scribe and Gemini transcribe nothing useful, the user's turn is still saved (empty transcript, audio kept for review) and the UI shows a clear note alongside it; the AI still responds and the round continues — a failed transcription never blocks the round, and never silently records a "blank speech" as if it were real content.
- A near-empty transcription (under ~10 chars) is treated as "no clear speech detected," with the recording retained and a friendly message shown.

### 6.4a Argument Rewrite Assistant (live before/after)
This is one of the product's headline features: a way to *show* Gemini improving reasoning, not just score it.

- After the user's transcript for a turn appears on screen, an **"Improve my argument"** button sits next to it — available at any point during the round, on any of the user's own turns, not just at the end.
- Tapping it sends the user's transcript (plus motion, side, and current phase for context — persona/difficulty are not needed here, since this is coaching the user, not the AI opponent) to Gemini with an explicit "strengthen this argument" instruction.
- The rewrite prompt is **phase-aware**: in Opening it rebuilds the constructive case (framework, definitions, mechanisms); in Rebuttal it sharpens surgical clash (targets the opponent's weakest link); in Closing it focuses on impact comparison and persuasive framing (see §12.1).
- Response renders as a **full-width rewrite card** directly below the user's turn actions bar:
  - Header badge: "Enhanced Version"
  - Main text: Gemini's rewritten version of the argument.
  - Below: 2–3 short bullets naming what changed (e.g. "added a concrete example," "removed a circular claim," "led with the strongest point first").
  - Note: Since the user's original speech is already displayed in the chat feed directly above the card, the redundant "Original" column was streamlined out for a clean, full-width presentation.
- This is a **coaching tool, not a resubmission** — the rewritten version is not spoken aloud, does not replace the user's turn in the transcript sent to the AI opponent, and is not scored differently by the adjudicator. It exists purely to show the user (and, live, the judges) what a stronger version of their own argument looks like.
- Designed to be fast to trigger live on stage: presenter picks a deliberately weak Opening statement, taps the button, and the before/after appears in a few seconds — this is the single best "show, don't tell" moment for demonstrating Gemini's reasoning quality.

### 6.5 AI Opponent (Gemini)
- One Gemini call per AI turn, given: motion, AI's side, persona system prompt, difficulty modifier, mode (tournament/sparring), full debate transcript so far, current phase (or, in Sparring Mode, the last few turns and any detected contradiction), and target language.
- Output: in-character spoken-style argument text (concise, spoken-register, not essay-style) in the selected language.
- Response should explicitly reference at least one point the user made when in Rebuttal or Closing phase (Tournament Mode) or in any turn after the first (Sparring Mode), to feel like real engagement, not a canned speech.

### 6.6 AI Opponent Voice (ElevenLabs + free fallback chain)
- Each persona is mapped to a specific ElevenLabs voice ID (configured in the `personas` table, selected from the team's ElevenLabs voice library). Difficulty does not change the voice — only persona does.
- Model selection is language-aware: `eleven_multilingual_v2` for Bangla (best Bangla phonetics), `eleven_turbo_v2_5` for English — same persona voice in both cases.
- Text sent to TTS is trimmed to a configurable max length (`ELEVENLABS_TTS_MAX_CHARS`, default 1500) at a sentence boundary; the full transcript is unaffected.
- **Quota handling:** when ElevenLabs reports `quota_exceeded` with "You have N credits remaining," the request is retried once with text trimmed to fit the remaining credits — a debate never runs silent because credits ran low.
- **Fallback chain (always, server-side):**
  1. ElevenLabs TTS → 2. **Google Translate TTS** (free public endpoint, pure PHP, no API key, chunks long text at ~190-char sentence boundaries and concatenates) → 3. **Silent fallback**: the AI's text is still shown in the transcript and the round continues; only the audio playback is skipped. A visible "TTS unavailable" style notice accompanies the fallback audio.
- Audio is streamed/returned to the Blade page and auto-played immediately after the AI's text appears, with a replay button.

### 6.7 Adjudication Engine
- Triggered once both sides have completed all three phases (Tournament Mode) or the user ends the round (Sparring Mode).
- Single Gemini call (or structured multi-part call) given the full transcript, motion, mode, and each side's identity.
- Output is **structured JSON** (see §12.1) parsed into:
  - Matter / Manner / Method sub-scores for the user (out of 40/30/30)
  - A total score out of 100
  - A list of flagged logical fallacies with the phase they occurred in and a one-line explanation
  - 3–5 bullet points of written feedback ("what worked," "what to improve")
  - A verdict line (who "won" the round, framed as practice feedback, not an absolute judgment)
- Rendered as a clean, printable/shareable "feedback sheet" screen.
- Rewrite-suggestion usage during the round does not factor into scoring — it's a learning aid, not a shortcut.

### 6.8 Language Support (English / Bangla)
- The debate's **language is data, not UI chrome**: the user picks one of two options at setup — **English** (`en`) or **Bangla** (`bn`). It is stored on the `debates` row and threaded through every backend call (motion generation, Gemini debate turns, rewrite suggestions, adjudication, TTS model choice) as an explicit parameter — never inferred or auto-detected mid-debate.
- UI strings are hardcoded in English (deliberately — full i18n was descoped in favor of per-debate content language; see §17).
- Font stack loads **Inter + Noto Sans Bengali** from Google Fonts so Bangla renders correctly alongside Latin script on every screen (including the feedback sheet's score labels).
- Debate content is transcribed via Scribe (language pinned to en/ben) with Gemini multimodal audio as fallback (see §12.1).

### 6.9 Feedback Sheet / Report
- On-screen summary card: scores (Tailwind-only progress bars per category, no charting library — see §9), fallacies list, feedback bullets, verdict.
- "Copy transcript" and "Download transcript" actions (plain-text `.txt`, served by the transcript route).
- "Start new round" resets state and returns to setup screen.

---

## 7. Non-Functional Requirements

- **Latency:** target under ~8–10 seconds from end of user speech to AI voice starting playback (transcription + generation + TTS combined); rewrite suggestions target under ~5 seconds since they're a shorter, single-purpose Gemini call. Show a clear "opponent is thinking" / "sharpening your argument" loading state during these gaps.
- **Security:** Gemini and ElevenLabs API keys live only in the Laravel `.env` and are used only inside Laravel controllers/services. Because there is no separate JS frontend or client-side API layer, keys and third-party calls never touch the browser — all requests from the page (audio upload, rewrite requests, polling) hit same-origin Laravel routes only.
- **Browser support:** modern Chromium-based browsers required for `MediaRecorder`; graceful fallback messaging if unsupported.
- **Accessibility:** every spoken AI turn has a visible text transcript; UI is keyboard-navigable; sufficient color contrast.
- **Reliability:** every third-party failure has a defined recovery path — STT: ElevenLabs Scribe → Gemini multimodal audio → turn saved with empty transcript + note (round continues); TTS: ElevenLabs (incl. quota-trim retry) → Google Translate TTS → text-only; Gemini text generation → friendly error message with retry, round state preserved. Partial results are always returned to the page; a single failed call never breaks the round.
- **Data retention:** for MVP, debate sessions can be stored server-side keyed to a session ID with no PII required (no login).

---

## 8. System Architecture

Rostrum is a **single Laravel application**. There is no separate frontend framework, no separate API domain, and no client-side state library — Blade views (styled with Tailwind CSS) are rendered server-side, and a thin layer of Alpine.js handles the small amount of client interactivity (recording audio, running the countdown timer, swapping in new transcript/audio HTML returned from the server).

```
┌───────────────────────────────────────────┐        HTTPS/JSON (same-origin)
│         Laravel App (monolith)             │◀──────────────────────────────┐
│  Blade views + Tailwind CSS + Alpine.js    │                                │
│  (server-rendered pages; Alpine handles    │                                │
│   mic recording, timers, DOM swaps)        │────────────────────────────────┘
└───────────────┬─────────────────────────────┘
                │  Controllers call out server-side only
                ▼
   ┌─────────────────────┐        ┌─────────────────────┐
   │   Gemini API         │        │  ElevenLabs API      │
   │ (motion generation,  │        │  (TTS per persona +  │
   │  debate turns,        │        │   Scribe STT)        │
   │  audio transcription │        └──────────┬──────────┘
   │  fallback, rewrite,  │                   │ fallback
   │  adjudication)        │                   ▼
   └─────────────────────┘        ┌─────────────────────┐
                                  │  Google Translate    │
                                  │  TTS (free, pure PHP)│
                                  └─────────────────────┘
```

**Per-turn flow (Tournament Mode):**
1. User speaks → Alpine.js component (on the Blade debate page) records audio via `MediaRecorder`.
2. Alpine `fetch`es the audio blob + phase to a Laravel route (same origin, no CORS needed).
3. Laravel saves the user audio locally, sends it to ElevenLabs Scribe for transcription.
4. **STT fallback:** if Scribe fails or returns near-empty text, Laravel sends the raw audio to Gemini multimodal → Gemini returns both the transcript and the AI's in-character response in one call (skips steps 5).
5. Otherwise Laravel calls Gemini with the transcript + full prior transcript → AI response text.
6. Laravel synthesizes the AI's voice: ElevenLabs (with quota-aware trim) → on failure **Google Translate TTS** → on failure, text-only silent fallback.
7. Laravel stores the turns (user transcript + AI text + AI audio URL), advances the phase if the round is complete, and returns JSON — **partial results always included** (e.g. `stt_error`/`ai_error` fields alongside whatever succeeded).
8. Alpine.js swaps the new transcript HTML into the page and plays the AI audio — no full page reload, no SPA framework.

**Rewrite-suggestion flow** follows the same shape: Alpine posts the turn's transcript to a Laravel route → Laravel calls Gemini (phase-aware, see §12.1) → returns the before/after JSON → Alpine renders the full-width rewrite card in place. Rewrites are cached per turn, so repeat requests are instant.

---

## 9. Tech Stack

**Frontend (server-rendered, no separate framework)**
- **Laravel Blade** for all views/templates — no Nuxt, no Vue, no React, no separate SPA.
- **Tailwind CSS v4.x** (latest stable, e.g. 4.3.x) for styling, via the official Vite/Tailwind integration Laravel ships with.
- **Alpine.js** for the light client-side interactivity Blade alone can't do: `MediaRecorder`-based mic capture, countdown timers, and swapping server-returned HTML/JSON into the page after each turn. No client-side state library (no Pinia/Redux) — state lives in the Laravel session/DB and is re-rendered server-side.
- **No charting library.** The Matter/Manner/Method + total score visualization on the feedback sheet is plain Tailwind (e.g. width-animated `<div>` bars driven by the score values the Blade view already has server-side). This avoids pulling in Chart.js just to draw three bars and a total — one less dependency to wire up in a 6-hour build, and it stays consistent with the "no unnecessary JS" spirit of dropping the SPA framework in the first place.
- Noto Sans Bengali (or similar) web font for Bangla rendering.

**Backend**
- **Laravel 13.x** (latest stable, PHP 8.3+ required).
- Laravel's HTTP Client (Guzzle) for calling Gemini and ElevenLabs REST APIs — always from server-side controllers/services, never from the browser.
- SQLite for hackathon simplicity (swap to MySQL/Postgres post-hackathon).
- Session-token-based debate identification for MVP (no full auth, no Sanctum needed since there's no separate API client). A `session_id` column on `debates` scopes the History screen; a seeded demo debate uses a reserved session id (`config/debate.php` → `seed_session_id`) so it appears in every visitor's history.
- File storage (local disk) for cached TTS audio per turn and for user recordings (both saved under `storage/app/public/audio/`).
- UI strings hardcoded in English; debate content language (en/bn) is data-driven per debate and threaded into every Gemini/ElevenLabs call — no i18n library, no `lang/` files (see §6.8, §17).

**Third-party APIs**
- Google Gemini API (via Google AI Studio) — multimodal (audio input), text generation, JSON-structured output for adjudication and rewrite suggestions.
- ElevenLabs API — multilingual text-to-speech, one voice ID per persona.

---

## 10. Data Model (Laravel schema)

All models use Laravel's `HasUuids` trait (auto-generated UUID primary keys — no manual ID assignment). Child tables (`rounds`, `turns`, `turn_rewrites`, `adjudications`) declare **`ON DELETE CASCADE`** foreign keys, so deleting a debate removes its entire tree automatically (applied via migration `2026_08_17_000008_harden_schema_constraints` on existing SQLite DBs and in the base migrations for fresh installs).

```php
// motions
Schema::create('motions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->text('text_en')->nullable();
    $table->text('text_bn')->nullable();
    $table->string('category')->nullable();
    $table->enum('source', ['manual', 'generated']);
    $table->timestamps();
});

// personas
Schema::create('personas', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');                 // e.g. "Calm Logician"
    $table->text('description');
    $table->text('system_prompt');           // persona behavior instructions (style)
    $table->string('elevenlabs_voice_id');
    $table->timestamps();
});

// debates
Schema::create('debates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('session_id');            // anonymous session key, no login required
    $table->uuid('motion_id');
    $table->enum('user_side', ['government', 'opposition']);
    $table->uuid('persona_id');
    $table->enum('difficulty', ['beginner', 'intermediate', 'advanced', 'world_champion'])
          ->default('intermediate');          // orthogonal to persona — skill level, not style
    $table->enum('mode', ['tournament', 'sparring'])->default('tournament');
    $table->enum('language', ['en', 'bn']);
    $table->enum('status', ['setup', 'in_progress', 'adjudicated'])->default('setup');
    $table->timestamps();

    $table->foreign('motion_id')->references('id')->on('motions');
    $table->foreign('persona_id')->references('id')->on('personas');
});

// rounds (one row per phase per debate — Tournament Mode; Sparring Mode uses a single
// implicit "round" row per debate and relies on turn ordering instead of fixed phases)
Schema::create('rounds', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('debate_id');
    $table->enum('phase', ['opening', 'rebuttal', 'closing'])->nullable(); // null in Sparring Mode
    $table->integer('phase_order');
    $table->timestamps();

    $table->foreign('debate_id')->references('id')->on('debates')->cascadeOnDelete();
});

// turns (one row per speech, user or AI)
Schema::create('turns', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('round_id');
    $table->enum('speaker', ['user', 'ai']);
    $table->text('transcript');
    $table->string('audio_path')->nullable();  // AI turns only
    $table->string('ai_move_type')->nullable(); // Sparring Mode only
    $table->timestamps();

    $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
});

// turn_rewrites (one row per "Improve my argument" request against a user turn)
Schema::create('turn_rewrites', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('turn_id');
    $table->text('original_text');
    $table->text('rewritten_text');
    $table->json('explanation_bullets');       // e.g. ["added a concrete example", ...]
    $table->timestamps();

    $table->foreign('turn_id')->references('id')->on('turns')->cascadeOnDelete();
});

// adjudications
Schema::create('adjudications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('debate_id');
    $table->integer('matter_score');
    $table->integer('manner_score');
    $table->integer('method_score');
    $table->integer('total_score');
    $table->json('fallacies');             // [{phase, type, explanation}]
    $table->json('feedback_bullets');
    $table->string('verdict');
    $table->timestamps();

    $table->foreign('debate_id')->references('id')->on('debates')->cascadeOnDelete();
});
```

*Note: difficulty tiers are implemented as prompt-fragment constants in `config/debate.php` (see §14), not a DB table — this keeps the difficulty axis cheap to build (no admin UI needed) while still being fully data-driven for the persona side, which does benefit from DB-backed editable prompts. Tuning knobs also live in `config/debate.php`: `phase_duration` (180/120/120s), `ai_word_count` (150–220 opening, 100–160 rebuttal/closing/sparring), `tts_max_chars`, and `seed_session_id`.*

---

## 11. Route / Endpoint Specification (Laravel, same-origin)

All of these are ordinary Laravel routes on the same app that serves the Blade pages — there is no separate API host, no CORS configuration, and no client-side API client library. Alpine.js components call these via `fetch()` (or plain form posts) and swap the returned HTML/JSON into the current page. Route parameters use implicit model binding (`/debates/{debate}`).

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/` | Home / landing (Blade view) |
| `GET` | `/setup` | Setup screen: language, motion, side, persona, difficulty, mode (Blade view) |
| `POST` | `/motions/generate` | Body: `{ category?, language }` → returns `{ motion }` (Gemini, with offline fallback sample motions) |
| `POST` | `/debates` | Body: `{ motion_text or motion_id, user_side or "auto", persona_id, difficulty, mode, language }` → creates debate (incl. seeded first round), redirects to `/debates/{id}` |
| `GET` | `/debates/{debate}` | Debate screen (Blade view) with full current state: motion, side, persona, difficulty, mode, all rounds/turns so far |
| `POST` | `/debates/{debate}/turns` | Multipart: audio blob + `{ phase, ai_first? }` → transcribes user speech (Scribe → Gemini audio fallback), generates AI counter-turn, synthesizes AI voice (ElevenLabs → Google TTS → silent); returns `{ user_transcript, user_audio_url, stt_error, ai_text, ai_audio_url, ai_error, round_complete, debate_complete, new_phase, next_speaker }` — partial results always included |
| `POST` | `/debates/{debate}/turns/{turn}/rewrite` | No body needed beyond the turn ID → returns `{ original_text, rewritten_text, explanation_bullets }` (cached per turn; only user turns) |
| `POST` | `/debates/{debate}/adjudicate` | Triggered after final phase (or user ends a Sparring round) → creates the adjudication row, marks debate `adjudicated`, redirects to feedback screen |
| `GET` | `/debates/{debate}/feedback` | Adjudication feedback sheet (Blade view) |
| `GET` | `/debates/{debate}/transcript` | Returns full plain-text transcript as a downloadable `.txt` file |
| `GET` | `/history` | History screen: session's debates + the seeded demo debate (Blade view) |
| `DELETE` | `/debates/{debate}` | Delete a debate (children removed via `ON DELETE CASCADE`), redirects back to history |

---

## 12. Third-Party Integration Details

### 12.1 Gemini API — prompt architecture
Five distinct prompt "modes," all going through the same Gemini endpoint but with different system instructions:

**A. Motion generation**
> "Generate one debatable, balanced motion in the style of '{category}' for a university debate. Phrase it as 'This House believes...'. Respond in {language}. Output only the motion, no preamble."

**B. Debate turn (opponent response)**
System prompt assembled from: persona's `system_prompt` (style) + the difficulty tier's prompt fragment (skill level, see §14) + fixed instructions:
> "You are debating the motion: '{motion}'. You are arguing the {ai_side} side. {phase_or_mode_instruction}. Respond as your persona would, at your assigned skill level — in spoken debate register, {word_count_range} words, in {language}. Directly engage with the user's most recent point where relevant. Do not break character or mention you are an AI."
>
> In Tournament Mode, `{phase_or_mode_instruction}` is an explicit, phase-specific strategy block (`buildPhaseInstruction`): **Opening** = deliver a constructive opening speech (establish framework/principles, define or scope the motion, 2–3 fully developed constructive arguments — do not reply to the opposition yet); **Rebuttal** = second-speaker clash (engage the opposing speaker's points point-by-point, attack their weakest premise or clearest logical gap, reinforce your own core case — do not repeat the opening); **Closing** = final reply speech (no new arguments; crystallize the core clash, compare whose arguments survive scrutiny, settle outstanding opposition points, close on why your side won). `{word_count_range}` scales to that phase's time budget: **Opening ~150–220 words, Rebuttal/Closing ~100–160 words** (config-driven in `config/debate.php`). In Sparring Mode, `{phase_or_mode_instruction}` instructs the model to pick one move type — follow-up question, contradiction callout, live challenge, or standard rebuttal — based on the transcript so far, to tag which one it used by opening with `[FOLLOW-UP]` / `[CONTRADICTION]` / `[CHALLENGE]` / `[REBUTTAL]` (the tag is parsed out and stored in `turns.ai_move_type`), and `{word_count_range}` stays at the shorter range (~100–160 words) throughout.

Input includes the full prior transcript (all turns so far, each labeled with its phase via `Debate::buildTranscript()` — e.g. `[OPENING PHASE] You (government): ...`) plus the user's transcript for this turn.

**B2. Audio fallback (Gemini multimodal — single-call transcribe + respond)**
> "The following audio is from a student speaking in a debate on the motion: '{motion}'... First, transcribe exactly what the student said... Then, as the opponent ({ai_side} side), generate your debate response... Output ONLY valid JSON: { transcript, ai_text }"

Used only when ElevenLabs Scribe STT fails or returns near-empty text (§6.4). The raw audio (base64, `audio/webm`) is sent inline with the same phase-aware instruction; Gemini returns both the user's exact words and the AI's response in one multimodal call, so the round loses no latency and the AI turn can still be generated.

**C. Argument rewrite suggestion**
> "The following is one turn from a student practicing debate on the motion '{motion}'. Rewrite it to be a meaningfully stronger version of the *same* argument — same core position, sharper structure, better evidence or examples, clearer logic. Do not change what side they're arguing. Respond in {language}. Output ONLY valid JSON matching this schema: { rewritten_text: string, explanation_bullets: [string] } — explanation_bullets should be 2–3 short phrases naming what changed."

The prompt is phase-aware (see §6.4a): it names the phase the turn belongs to and re-targets the rewrite accordingly (Opening → strengthen framework/constructive case; Rebuttal → sharper surgical clash; Closing → impact comparison and persuasive framing).

**D. Adjudication** — must request strict JSON output:
> "You are an experienced debate adjudicator scoring the {user_side} speaker on the motion '{motion}'. Score Matter (/40), Manner (/30), Method (/30). Identify any logical fallacies used by the user speaker, citing the phase. Give 3–5 concise, actionable feedback bullets that quote the exact user phrase from the transcript and explain what to improve. Include a final bullet prescribing one specific daily practice drill. Give a one-line verdict. Respond in {language}. Output ONLY valid JSON matching this schema: { matter_score, manner_score, method_score, total_score, fallacies: [{phase, type, explanation}], feedback_bullets: [string], verdict: string }"

Laravel parses this JSON directly into the `adjudications` / `turn_rewrites` tables; a `parseJson` repair helper strips stray markdown code fences, and on decode failure it falls back to defaults (logged) rather than crashing the request.

### 12.2 ElevenLabs — voice mapping & TTS pipeline
- Each `persona` row stores one `elevenlabs_voice_id` (e.g. Calm Logician → `pNInz6obpgDQGcFmaJgB`, Aggressive Cross-Examiner → `EXAVITQu4vr4xnSDxMaL`, Devil's Advocate → `TX3LPaxmHKxFdv7VOQHJ`), selected ahead of time from the team's ElevenLabs voice library to match the character described in §6.2. Difficulty does not affect voice selection.
- Model is chosen per language: `eleven_multilingual_v2` for Bangla (best Bangla phonetics with any voice), `eleven_turbo_v2_5` for English — the same voice ID works for both, no voice switching.
- Laravel calls ElevenLabs' text-to-speech endpoint with the AI's generated text (pre-trimmed to `tts_max_chars` at a sentence boundary; retried with a credit-budget-trimmed text on `quota_exceeded`) and stores the returned audio locally under `storage/app/public/audio/`, returning a URL the Blade page can stream immediately.
- **STT:** ElevenLabs Scribe (`scribe_v1`) transcribes the user's audio; `language_code` is pinned for en/bn.

### 12.3 Resilience & Fallback Chain (applies to every third-party call)
1. **Motion generation:** Gemini fails → returns a random motion from curated per-language fallback lists (`en`, `bn`).
2. **STT:** Scribe fails/empty → Gemini multimodal audio (transcribe + respond, single call) → still failing, the user's turn is saved with an empty transcript (audio kept) and the UI shows a clear note; AI still responds; round continues.
3. **TTS:** ElevenLabs fails (or quota can't fit) → Google Translate TTS (free endpoint, pure PHP, chunked at ~190 chars) → silent text-only fallback with a visible notice; the AI's text is always shown regardless.
4. **Gemini text generation:** transient errors are retried automatically (up to 5 retries with backoff on 429/5xx); persistent failure returns a friendly, human-readable error message to the page — never a raw exception — and the round state stays intact for retry.
5. **Turns are persisted independently:** user turn, AI text, AI audio, phase advancement, and the JSON response each succeed or fail on their own; whatever succeeded is returned to the client (`stt_error` / `ai_error` fields carry the rest).

---

## 13. Debate Format Specification

- **Format base:** Asian Parliamentary, adapted for 1v1 solo practice (see design note in §6.3).
- **Sides:** Government (Proposition) argues for the motion; Opposition argues against.
- **Modes:**
  - **Tournament Mode (default, P0):** Speaking order — Government speaks first in Opening and Rebuttal (matches real AP convention where Proposition opens); **Closing reverses order** so whichever side spoke second in Opening speaks first in Closing (matches real AP reply-speech convention, preventing the same side from having the last word twice). Phases: Opening (3 min) → Rebuttal (2 min) → Closing (2 min), one speech per side per phase. Timer behavior: visible countdown; warning state at 30s remaining; auto-stop recording at 0. The setup screen shows a **standards note** explaining these rules (and that Sparring has no fixed format), so first-time users understand the format before the round starts.
  - **Sparring Mode (stretch, P1/P2):** No fixed phases or hard time limits per turn; the AI picks a follow-up question, contradiction callout, live challenge, or standard rebuttal after each user turn (see §6.3a). The user ends the round manually when ready to be adjudicated.
- **Difficulty:** Beginner → Intermediate → Advanced → World Champion, orthogonal to mode and persona (see §6.2a).
- **No POIs in MVP** — flagged as a stretch goal (see §19).

---

## 14. AI Persona & Difficulty Specifications

**Persona** (`system_prompt`, stored in DB, editable without redeploying) encodes:
1. Tone and pacing of argument.
2. How aggressively it challenges the user's premises.
3. Characteristic rhetorical moves (e.g., Devil's Advocate deliberately steelmans the least popular counter-position; Aggressive Cross-Examiner opens with a direct challenge to the user's weakest claim; Calm Logician structures arguments as clearly numbered premises).
4. Explicit instruction to stay in character and never reference being an AI system.

**Difficulty** (config-based prompt fragment, appended to whichever persona is selected) encodes:
1. Depth and specificity of evidence/examples used.
2. How actively the AI hunts for and exploits weaknesses or contradictions in the user's case.
3. Vocabulary and sentence complexity.
4. At World Champion, an explicit instruction to identify the single weakest link in the user's most recent turn and target it directly; at Beginner, an explicit instruction to argue simply and let minor weaknesses in the user's case go unchallenged.

Persona and difficulty are independent selections — the persona card grid and the difficulty selector are separate, unlinked UI controls (see §16).

---

## 15. Adjudication Rubric

| Category | Weight | What it measures |
|---|---|---|
| **Matter** | 40 pts | Quality, relevance, and logical soundness of arguments and evidence used |
| **Manner** | 30 pts | Persuasiveness, clarity, and rhetorical delivery (inferred from language used in the transcript for MVP; full prosody/tone-of-voice analysis is a stretch goal) |
| **Method** | 30 pts | Structure, organization, time use, and how directly the speaker engaged with and rebutted the opponent |

Fallacy detection covers common types relevant to debate (e.g. strawman, ad hominem, false dichotomy, slippery slope, appeal to emotion) — each instance tagged with the phase it occurred in and a short explanation, not a lecture.

Use of the rewrite assistant (§6.4a) during the round does not affect scoring — it's scored on what the user actually said, not on rewritten versions they viewed but didn't (and can't) resubmit.

---

## 16. UI/UX Screens

1. **Home / Landing** — hero headline, one-line pitch, stats row (3 phases / 3 AI personas / 100-point rubric / 2 languages), "How it works" four-step grid, "Begin a Debate Session" CTA.
2. **Setup screen** — language cards (English / বাংলা — "Affects audio, transcription & AI responses"), motion input/generate (with category dropdown), side picker (Government / Opposition / Surprise me), persona card grid (clean border highlight selection without checkmarks), difficulty selector (Beginner → World Champion, each with a short descriptor), mode toggle (Tournament / Sparring) with a live **standards note** under it explaining the format, submit CTA.
3. **Debate screen** — motion banner (persistent), phase indicator (Opening/Rebuttal/Closing with progress dots in Tournament Mode; a simple turn counter in Sparring Mode), timer (Tournament Mode), big mic button, live transcript area (user + AI), an **"Improve my argument"** button next to each of the user's own turns that opens the before/after rewrite panel, "opponent is thinking" loading state, auto-playing AI audio with a replay button, status bubbles that are cleared between turns. Responsive: stacks into a single column below 900px.
4. **Adjudication / Feedback sheet** — score bars (Matter/Manner/Method + total, plain Tailwind, no charting library), fallacies list, feedback bullets, verdict banner, transcript download action, "Start new round" CTA.
5. **History screen** (nav link) — list of the session's past debates (plus the seeded demo debate), each showing motion, persona, difficulty, mode, language, status, and score when adjudicated; reopen a debate, download its transcript, or delete the session (cascading).
6. **Global UI Components** — Global confirmation modal centered in middle of viewport, and Toast notification container fixed in bottom-right corner across all views.

Use the `frontend-design` conventions for visual polish (typography, spacing, avoiding generic templated look) when this is actually built. Everything here is a Blade view — no separate component library or client router.

---

## 17. Localization Strategy

- **Two Core Supported Languages:** The platform supports **English (`en`)** and **Bangla (`bn`)** debate content modes (validated via `SetupController` and enforced via `debates.language IN ('en', 'bn')` CHECK constraint).
- **UI strings are hardcoded in English** in Blade views. Full i18n (Laravel `lang/*.php` + `__()` everywhere) was descoped: the product's "language" is the debate's *content* language, and every user-facing flow works in English with Bangla debate content.
- Every backend call that touches Gemini/ElevenLabs takes an explicit `language` parameter driven by the debate's `language` column (`en` / `bn`) — never auto-detected from speech, to avoid inconsistent mid-debate language switches.
- Bangla rendering is verified across all screens via the Noto Sans Bengali web font (Inter + Noto Sans Bengali loaded from Google Fonts in the layout), including the feedback sheet's score labels.
- Natural Bangla/English code-switching within a single spoken turn (common among Bangladeshi debaters) is handled natively when Gemini's multimodal audio fallback transcribes it (see §12.1).
---

## 18. MVP Build Plan (6-hour hackathon window, 10:00–16:00)

| Time | Focus | Priority |
|---|---|---|
| 10:00–10:30 | Laravel 13 app skeleton: migrations (incl. `difficulty`, `mode`, `turn_rewrites`), routes stubbed, Tailwind + Alpine wired into the default Blade layout | P0 |
| 10:30–11:15 | Motion input + `/motions/generate` wired to Gemini; setup screen (motion, side, persona, difficulty) as a Blade view | P0 |
| 11:15–12:15 | Debate screen scaffolding + `/debates` creation route + Tournament Mode turn loop (record → transcribe+respond → TTS → playback) | P0 |
| 12:15–12:45 | **Rewrite assistant**: `/debates/{debate}/turns/{turn}/rewrite` route + before/after panel in the debate screen | P0 |
| 12:45–13:15 | Lunch/buffer | — |
| 13:15–14:00 | Adjudication endpoint + feedback sheet screen | P0 |
| 14:00–14:30 | Bangla localization pass across all screens + prompts | P0 |
| 14:30–15:00 | Polish pass: difficulty selector UX, timer states, error/retry states | P0 |
| 15:00–15:45 | Deploy, record 3-min demo video (make sure it shows the rewrite before/after live), write Devpost submission | P0 |

**If ahead of schedule (P1 stretch):** Sparring Mode (§6.3a), live captions during recording, download-as-PDF feedback sheet, voice preview clips on persona picker.
**If time allows further (P2 stretch):** POIs, motion category browsing, transcript sharing link.

*Status note: this section is the original as-written hackathon plan. Every P0 line item shipped, plus Sparring Mode and the History screen (neither a P0). Live captions, PDF feedback, and voice preview clips did not ship and remain P1.*

---

## 19. Post-Hackathon Roadmap (not for the event submission)

- Full Sparring Mode refinement (better contradiction detection across a longer transcript, tunable aggressiveness).
- Points of Information (POIs) — AI can interject briefly during user speeches.
- Full 4-speaker team format (multi-agent, one Gemini persona per bench position).
- User accounts + practice history/progress tracking over time, including tracking whether a user's later arguments start to resemble past rewrite suggestions (a genuine "did you learn from this" signal).
- Prosody-aware Manner scoring (analyzing actual vocal delivery, not just transcript).
- Tournament-style ladder against increasingly difficult AI personas (natural extension of the difficulty axis).

---

## 20. Judging Criteria Alignment

- **Best Use of Gemini API:** Gemini powers motion generation, in-character multi-turn debate reasoning with persona *and* difficulty consistency, live argument rewriting (a direct, visible demonstration of reasoning quality — not just a score), audio transcription, and structured JSON adjudication — well beyond a basic chat box.
- **ElevenLabs Side Track:** every AI turn is spoken aloud in a distinct, persona-matched multilingual voice — a core, non-optional part of the experience, not a bolt-on feature.
- **Antigravity workflow credit:** if built using Antigravity, capture and include the agent's plan → execution → verification trail in the Devpost submission, per the event's explicit ask to show workflow, not just output.
- **Overall / local-impact story:** directly serves Bangladesh's active university debate culture, in Bangla and English, for debaters at every skill level from first-timer to tournament-ready.

---

## 21. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Gemini/ElevenLabs latency breaks the "live sparring" feel | Clear "opponent is thinking" state; keep AI responses short (100–220 words depending on phase, see §12.1) to reduce TTS generation time |
| Rewrite-suggestion calls add a second latency point mid-round | Keep the rewrite prompt short and single-purpose (no TTS involved), target <5s; make it fully optional/on-demand so it never blocks the round's critical path; results are cached per turn |
| Bangla speech transcription accuracy lower than English | Rely on Scribe (pinned Bangla language code) with Gemini multimodal audio as an automatic fallback; the user's recording is always saved for review even when transcription fails |
| ElevenLabs outage / API key expiry / quota exhaustion mid-demo | Full TTS fallback chain: quota-aware text trimming + retry → free Google Translate TTS (pure PHP, no key) → silent text-only playback with a visible notice. A demo never runs fully silent |
| Adjudication or rewrite JSON malformed | Backend-side JSON repair step (strips markdown fences); strict schema instruction in the prompt for both; graceful defaults on decode failure |
| Live demo mic/audio failure in front of judges | Always have a pre-recorded 3-min demo video as backup, per submission requirements; plus the in-app "we didn't catch that" retry state |
| Sparring Mode's added branching logic eats into build time | Explicitly P1/P2 (§18) — Tournament Mode ships first and stands alone as a complete, demoable product even if Sparring Mode doesn't make it in |
| Scope too large for 6 hours | P0/P1/P2 triage in §18; a working English-only, one-persona, Beginner-difficulty-only, no-Sparring-Mode version (with the rewrite assistant intact, since it's the standout demo moment) is the true minimum fallback |

---

## 22. Appendix A: System Prompt Fragments (shipped — see `PersonaSeeder` and `config/debate.php`)

**Calm Logician (persona system_prompt, voice `pNInz6obpgDQGcFmaJgB`):**
> "You debate in a calm, analytical, and structured manner. Frame your response with clear premise-conclusion structures. Rely on empirical evidence, logical deduction, and structured points (e.g. 'First... Second...'). Never raise your tone or sound flustered. Maintain an unshakeable academic composure."

**Aggressive Cross-Examiner (voice `EXAVITQu4vr4xnSDxMaL`):**
> "You debate assertively and directly. Open by challenging the weakest part of your opponent's last point. Use short, punchy sentences. Do not soften your language, but stay respectful and never personal. Your pace is fast and your tone is high-energy — you are here to win, and you show it."

**Devil's Advocate (voice `TX3LPaxmHKxFdv7VOQHJ`):**
> "You deliberately argue the least comfortable, most contrarian defensible position. Use a wry, ironic tone. Point out inconvenient implications of your opponent's stance. You enjoy intellectual provocation — not to be annoying, but to expose the assumptions everyone is quietly ignoring."

**Difficulty fragments (all four, from `config/debate.php`, appended to whichever persona is selected):**
- **Beginner:** "Argue simply and clearly, as a friendly practice partner would. Let minor weaknesses in your opponent's case go unchallenged so they can build confidence. Use straightforward vocabulary and avoid complex rhetorical moves."
- **Intermediate:** "Argue at a solid club-level standard. Engage directly with your opponent's main points, use one or two concrete examples, and maintain a clear logical structure throughout your speech."
- **Advanced:** "Argue at a competitive tournament standard. Use precise evidence and real-world examples. Identify and target the weakest premise in your opponent's case. Employ sophisticated rhetorical structures (e.g. link-turns, impact calculus). Leave no major claim by your opponent unaddressed."
- **World Champion:** "Argue at a tournament-champion skill level. Use precise evidence and examples. Actively identify the single weakest link in your opponent's most recent point and target it directly. Employ advanced debate techniques: pre-empting likely responses, framing the round's core clash, and making explicit impact comparisons. Your arguments should be airtight."

## 23. Appendix B: Sample Motions

- This House believes social media does more harm than good.
- This House would ban private tutoring (coaching centers).
- This House believes AI-generated art should be eligible for copyright.
- This House would prioritize climate migrants in refugee policy.
- This House believes university admissions should be need-blind.

# Product Requirements Document: Rostrum

**AI-Powered Debate Practice Simulator**
Build target: Build With AI Hack Days @EMK, Aug 17, 2026
Status: Shipped — hackathon MVP plus the post-hackathon accounts, practice-analytics and stage-brief pass

> **How to read this document.** §1–§17 and §22–§23 are the specification as written for the 6-hour hackathon build, then revised where the shipped product diverged from it. Where a change was a deliberate departure from the original plan, the original is kept and the new behaviour is described alongside it. §18 carries a status note listing everything that shipped after the hackathon window. Section numbers are stable: nothing was renumbered, so cross-references still hold.

---

## 1. Overview

Rostrum is a web application that lets a student practice competitive debate against an AI opponent. The user picks or generates a motion, selects a side, an AI persona, and a difficulty level, then debates through a structured round format (Opening → Rebuttal → Closing) by speaking aloud. The AI opponent argues in character using Gemini and speaks back using a distinct ElevenLabs voice. After the round, Gemini acts as an adjudicator and produces a structured feedback sheet modeled on real debate judging (Matter / Manner / Method), including fallacy detection and a score. At any point during the round, the user can also ask Gemini to **rewrite one of their own arguments**, seeing a live before/after that demonstrates stronger reasoning — one of the product's strongest live-demo moments.

The product supports **English and Bangla** debate modes, end to end — motions, AI arguments, spoken voice, rewrite suggestions, and adjudication feedback.

Every practice belongs to an account. The Overview page turns a record of debates into a picture of a debater's practice — a score trend over time, where the marks are won, and how much of each phase's time was actually used. See §6.10.

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

### Goals (added after the hackathon build)
- A practice record that outlives a browser session: an account owns its debates, and the Overview page shows what the record says about the debater's progress.
- A report that tells a debater what to do next, not only where the points went.
- A debate engine whose speeches fit the round: the AI's reply answers the speech it is answering, and its closing weighs rather than introduces.

### Stretch Goal (design-in-now, build-if-time)
- **Sparring Mode**: an alternative, more conversational round format where the AI can ask follow-up questions, spot contradictions, and issue live challenges instead of trading fixed-length speeches. See §6.3a. This is architected as a `mode` selection from the start so it isn't a bolt-on later, but it is **not required for the hackathon submission** — Tournament Mode (the structured Opening/Rebuttal/Closing format) is the P0 default and must work end-to-end regardless of whether Sparring Mode ships.

### Non-Goals (explicitly out of scope for the hackathon build)
- Multiplayer / human-vs-human debate.
- Team debates (this is 1v1 solo practice, not full Asian Parliamentary 4-team format).
- Points of Information (POIs) / live interruptions during speeches — noted as a post-hackathon feature.
- Mobile native apps (responsive web only, server-rendered).
- Leaderboards / social features.

**Deliberately descoped for the hackathon build, and shipped afterwards.** These were listed as non-goals when this document was written for the 6-hour window. They were built in the post-hackathon pass and are specified in full in §6.10:

- **Persistent user accounts, login, and cross-device history** — session-based only for the hackathon submission; accounts, an account-scoped history and a practice dashboard shipped afterwards.
- **A shared public sample debate** — the seeded demo debate was originally pushed into every visitor's history. It is now owned by the seeded demo account alone, because an account's record is a record of that account's own practice (see §6.10).

---

## 4. Target Users

| Persona | Description | Primary Need |
|---|---|---|
| University debater (competitive) | Active member of a debate club, prepping for tournaments | Realistic rebuttal practice + rubric-accurate feedback + concrete examples of stronger phrasing |
| Debate-curious student | Never debated formally, curious to try | Low-friction onboarding, forgiving persona, Beginner difficulty, clear format explanation |
| Bangla-first speaker | More comfortable arguing in Bangla than English | Full-fidelity Bangla experience, not a translated afterthought |

---

## 5. Core User Journey

1. **Land on home screen** → short product pitch, stats (3 phases / 3 personas / 100-point rubric / 2 languages), "Begin a Debate Session" CTA. The landing page is public; everything behind it needs an account.
2. **Register or sign in** → `/register` or `/login`. A password-policy meter shows the requirements live. The sign-in screen carries a "Try the demo account" panel that fills the seeded credentials. On success the user lands on the **Overview** page (see §6.10).
3. **Set up the round**: choose **language** (English / Bangla — affects audio, transcription, and AI responses), enter a motion manually, or tap "Generate Motion" (Gemini generates one, optionally by topic/category).
4. **Choose side**: Government (Proposition) or Opposition — manual pick, or "Surprise me" (random assignment).
5. **Choose AI persona**: Calm Logician / Aggressive Cross-Examiner / Devil's Advocate — this controls *style*.
6. **Choose difficulty**: Beginner / Intermediate / Advanced / World Champion — this controls *skill level*, independent of persona (see §6.2a).
7. **Choose mode**: Tournament (structured, timed speeches — default) or Sparring (adaptive, conversational — see §6.3a). A standards note under the mode cards explains which debating format each mode follows (WSDC/AP for Tournament — including the Closing-order reversal — no fixed format for Sparring).
8. **Debate begins** — phase indicator shows Opening → Rebuttal → Closing (Tournament Mode), with a timer per speech.
9. Each phase: user taps mic, speaks, taps stop → sees their live transcript → optionally taps **"Improve my argument"** to see a Gemini rewrite of what they just said (§6.4a) → AI's turn plays automatically (text appears + voice plays).
10. After Closing phase completes both sides → **Adjudication screen** loads: scores, fallacies flagged, written feedback, verdict.
11. User can **download the transcript** as a `.txt` file, view the feedback sheet again, or **start a new round**. Past debates appear on the **History screen** (visible in the nav), where any session can be reopened or deleted. The **Overview** page shows the account's practice record — score trend, rubric averages, reports by difficulty (see §6.10).

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
- Tapping it sends the user's transcript (plus motion, side, and the turn's phase **and slot** for context — persona/difficulty are not needed here, since this is coaching the user, not the AI opponent) to Gemini with an explicit "strengthen this argument" instruction.
- The rewrite prompt is **slot-aware**, not merely phase-aware: a phase holds two speeches and the rewrite has to fit the one being rewritten. In Opening it rebuilds the constructive case (framework, definitions, mechanisms); in Rebuttal it sharpens surgical clash (targets the opponent's weakest link); in Closing it focuses on impact comparison and persuasive framing (see §12.1). Within a phase, the second speaker's rewrite is held to a *reply* — it answers the speech it is answering rather than delivering a fresh constructive case, and the Closing's final reply is written to defeat the opponent's closing rather than to re-weigh the whole round. A reply that stays a reply; an opening that stays an opening.
- Response renders as a **full-width rewrite card** directly below the user's turn actions bar:
  - Header badge: "Enhanced Version"
  - Main text: Gemini's rewritten version of the argument.
  - Below: 2–3 short bullets naming what changed (e.g. "added a concrete example," "removed a circular claim," "led with the strongest point first").
  - Note: Since the user's original speech is already displayed in the chat feed directly above the card, the redundant "Original" column was streamlined out for a clean, full-width presentation.
- This is a **coaching tool, not a resubmission** — the rewritten version is not spoken aloud, does not replace the user's turn in the transcript sent to the AI opponent, and is not scored differently by the adjudicator. It exists purely to show the user (and, live, the judges) what a stronger version of their own argument looks like.
- Designed to be fast to trigger live on stage: presenter picks a deliberately weak Opening statement, taps the button, and the before/after appears in a few seconds — this is the single best "show, don't tell" moment for demonstrating Gemini's reasoning quality.

### 6.5 AI Opponent (Gemini)
- One Gemini call per AI turn, given: motion, AI's side, persona system prompt, the **debate register**, difficulty modifier, mode (tournament/sparring), full debate transcript so far, the **stage brief** (phase + slot + the speech being answered + what the speaker's own side has already established), and target language.
- The prompt is built from a **stage brief** (`App\Services\DebateStage`) rather than from the phase name alone. A phase holds two speeches and the two are not the same speech, so the brief answers four questions: which phase this is and what the phase is FOR in the round; which **slot** within the phase the speaker holds (first or second); which speech they are **answering**, quoted verbatim; and what their own side has already established. See §12.1 B.
- The slot is derived from the round's **actual turns**, not from a counter, so a round that was resumed, retried, or partly failed still produces a brief that describes the round as it actually is.
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

### 6.10 Accounts, Ownership & Practice Tracking (shipped post-hackathon)
The hackathon build scoped the app to a browser session. Accounts were built afterwards, and they changed the shape of the product: a debate is no longer a row keyed to a session, it is a record belonging to a person, and that record is worth reading over time.

**6.10a Account lifecycle**
- **Register / Sign In / Sign Out.** Registration creates the account and signs the user in; sign-in regenerates the session ID (no session fixation); sign-out invalidates the session and regenerates the CSRF token.
- **Password policy** — at least 10 characters with an upper case letter, a lower case letter and a digit, enforced on registration *and* on password change, with the requirements shown live on the credential screens.
- **Email normalisation** — addresses are lower-cased and trimmed before validation and before storage, so `Ada@Example.com` and `ada@example.com` cannot become two accounts.
- **Guest-session adoption** — the guest session id is captured *before* authentication (which regenerates it) and any debate created in that session under a null owner is claimed by the account. Debates with an existing owner are never claimed.
- **Account settings** (`/dashboard/settings`) — profile (name, email), password change, and account deletion. The page carries **no statistics**: a settings page that also reports your numbers asks for attention it has not earned, and the Overview already owns the full stat display.
- **Account deletion** cascades to every debate, round, turn, rewrite and adjudication the account owns.

**6.10b Ownership and authorisation**
- Every debate belongs to exactly one owner (`debates.user_id`). `debates.user_id` is **nullable at the schema level** so debates created before accounts existed are not lost — `SeedAccountSeeder` adopts those orphans and gives them an owner.
- Authorisation is enforced in one place — `App\Policies\DebatePolicy` — and applied as route middleware (`can:view,debate`, `can:participate,debate`, `can:adjudicate,debate`, `can:rewrite,debate`, `can:delete,debate`) as well as inside the controllers. Laravel resolves a policy method before a string gate of the same name, so all five abilities land on the policy.
- **There is no shared sample.** The seeded demo debate is owned by the demo account like any other debate; it is not pushed into anybody else's history. A newly registered account opens on an empty ledger.
- **Rate limiting is per account, not per IP** for the AI-backed endpoints, so one abusive account cannot exhaust the shared Gemini/ElevenLabs quota. Sign-in is additionally protected by a per-account lockout (5 failures → 15 minutes) counted in the cache, with route-level throttles as the backstop against a spray across many addresses.

**6.10c The Overview page**
- Every figure is scoped to the signed-in account, so a stat never counts somebody else's debate.
- **Metric strip** — debates, reports, average score and best score. The reports tile is counted from the *reports*, not from the debates: a debate in progress has no mark, and a note sitting under the reports count has to describe the same set the count names.
- **Score trend** — every scored debate plotted oldest-first, so the line reads left-to-right as time passing. Only scored debates carry a point, so an unfinished debate is a *gap* rather than a zero; plotting it as zero would draw a collapse in performance that never happened. The chart is drawn by `App\Support\ScoreTrend` as an SVG plot over a fixed 640 × 200 coordinate space, with gridlines at 0/25/50/75/100, each reading placed as real text above (or below) its point.
- **Rubric averages** — where the marks are won, each criterion read against its own maximum (Matter /40, Manner /30, Method /30) rather than against 100.
- **Reports by difficulty** — every difficulty is listed even when it has no reports yet, because a bar chart whose axis changes shape between visits is harder to read, not easier.
- **Readable without colour** — the trend's scale sits outside the plot and the readings are real text laid over it, so the chart never depends on colour alone, and it carries an `aria-label` stating the range and every score in the series.

**6.10d The report analysis engine**
`App\Support\ReportAnalysis` reads the adjudication and the transcript and returns everything the report page renders. Nothing in it is invented and nothing in it is stored — it is all derived at request time.

- **The brief** — four lines of ordinary language at the top: what the mark means, what worked, what cost the round the most, and the one thing to change. A reader who wants only the brief never has to scroll past it.
- **The grade ladder** — the whole scale with the mark placed on it, so the reader sees the round in the context of the rubric rather than as a bare number. Each band shows the points it starts at, how far through the band the mark sits, and how many points short of the band above it is.
- **Per-criterion ladders** — Matter, Manner and Method each carry their own band scale, because the criteria have different weights (40 / 30 / 30). Each score is shown as a *percentage of its own maximum*, so a 22/30 is correctly read as a stronger showing than a 22/40.
- **Findings, grouped and anchored** — the judge's bullets are parsed into `[STRENGTH]` / `[ISSUE]` / `[TIP]` / `[DRILL]` groups (an unrecognised tag becomes a *note* rather than being dropped) and each is anchored to the phase it refers to, so the reader knows which speech to reopen. A closing recommendation without a `[DRILL]` tag is promoted to the drill, since the prompt's contract puts the practice plan last.
- **Verified quotes only** — a quoted phrase is shown as the speaker's words only if it can actually be found in the speaker's own turns. The judge quotes the opponent often enough that an unverified attribution would put words in the speaker's mouth.
- **Delivery analysis** — estimated speaking time against each phase's budget, at 140 wpm. A phase answered in forty seconds out of a three-minute budget is a fact about the round the mark alone does not carry. Under 55% is reported as a shortfall, over 105% as an overrun.
- **Bangla-aware word counts** — Bangla is written without spaces between words, so a whitespace count would report one word for a whole paragraph. Counts fall back to characters (scaled by the ~5-character average Bangla word) when the debate is in Bangla.

---

## 7. Non-Functional Requirements

- **Latency:** target under ~8–10 seconds from end of user speech to AI voice starting playback (transcription + generation + TTS combined); rewrite suggestions target under ~5 seconds since they're a shorter, single-purpose Gemini call. Show a clear "opponent is thinking" / "sharpening your argument" loading state during these gaps.
- **Security:** Gemini and ElevenLabs API keys live only in the Laravel `.env` and are used only inside Laravel controllers/services. Because there is no separate JS frontend or client-side API layer, keys and third-party calls never touch the browser — all requests from the page (audio upload, rewrite requests, polling) hit same-origin Laravel routes only. **Outbound TLS verification is enabled** for every Gemini and ElevenLabs request (`Http::withoutVerifying()` was removed) — the Docker image installs `ca-certificates` so the chain can actually be validated. Every response carries `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Cross-Origin-Opener-Policy` and `Permissions-Policy` from `App\Http\Middleware\SecurityHeaders`. A Content-Security-Policy is deliberately **not** set: the app loads Google Fonts and uses inline Alpine.js expressions, so a strict CSP would need nonces threaded through every view.
- **Browser support:** modern Chromium-based browsers required for `MediaRecorder`; graceful fallback messaging if unsupported.
- **Accessibility:** every spoken AI turn has a visible text transcript; UI is keyboard-navigable; sufficient color contrast.
- **Reliability:** every third-party failure has a defined recovery path — STT: ElevenLabs Scribe → Gemini multimodal audio → turn saved with empty transcript + note (round continues); TTS: ElevenLabs (incl. quota-trim retry) → Google Translate TTS → text-only; Gemini text generation → friendly error message with retry, round state preserved. Partial results are always returned to the page; a single failed call never breaks the round.
- **Data retention:** debate sessions are stored server-side against the owning account, keyed by `debates.user_id`. `debates.session_id` is still recorded — it identifies the browser that started the debate, not the owner — and is kept for the guest-adoption path (see §6.10a). Voice recordings are stored on the **private** disk and are streamed back only through an ownership-checked endpoint; they are never served off the public disk, and the response is marked `Cache-Control: private`.

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

**Application layers (as shipped):**

| Layer | Class | Responsibility |
|---|---|---|
| Controllers | `DebateController`, `SetupController`, `HistoryController`, `DashboardController`, `AccountController`, `AuthController`, `HomeController` | The HTTP surface; every write route is behind `auth`, and every debate route behind `can:*` |
| Authorisation | `App\Policies\DebatePolicy` | Per-debate ownership — the only way to reach a debate |
| Round engine | `App\Services\DebateRoundEngine` | Phase order, the Closing reversal, round completion |
| Stage brief | `App\Services\DebateStage` | Phase **and slot** — which speech this speaker is making, which speech they are answering, what their own side has already said, what is left of the round |
| Prompting | `App\Services\GeminiService` | The five prompt modes (A/B/B2/C/D), the debate register, and the rubric written into the adjudication prompt |
| Voice | `App\Services\ElevenLabsService`, `App\Services\GoogleTtsService` | Scribe STT, TTS with quota-trim retry, and the free fallback |
| Analysis | `App\Support\ReportAnalysis`, `App\Support\ScoreTrend` | The report's derived brief/ladders/findings/delivery, and the SVG trend chart's geometry |
| Storage | Private disk | `storage/app/private/audio/` — user WebM and AI MP3, served only through the ownership-checked audio route |

**Per-turn flow (Tournament Mode):**
1. User speaks → Alpine.js component (on the Blade debate page) records audio via `MediaRecorder`.
2. Alpine `fetch`es the audio blob + phase to a Laravel route (same origin, no CORS needed).
3. Laravel saves the user audio locally, sends it to ElevenLabs Scribe for transcription.
4. **STT fallback:** if Scribe fails or returns near-empty text, Laravel sends the raw audio to Gemini multimodal → Gemini returns both the transcript and the AI's in-character response in one call (skips steps 5).
5. Otherwise Laravel calls Gemini with the transcript + full prior transcript → AI response text.
6. Laravel synthesizes the AI's voice: ElevenLabs (with quota-aware trim) → on failure **Google Translate TTS** → on failure, text-only silent fallback.
7. Laravel stores the turns (user transcript + AI text + AI audio URL), advances the phase if the round is complete, and returns JSON — **partial results always included** (e.g. `stt_error`/`ai_error` fields alongside whatever succeeded).
8. Alpine.js swaps the new transcript HTML into the page and plays the AI audio — no full page reload, no SPA framework.

**Rewrite-suggestion flow** follows the same shape: Alpine posts the turn's transcript to a Laravel route → Laravel calls Gemini (slot-aware, see §12.1) → returns the before/after JSON → Alpine renders the full-width rewrite card in place. Rewrites are cached per turn, so repeat requests are instant.

---

## 9. Tech Stack

**Frontend (server-rendered, no separate framework)**
- **Laravel Blade** for all views/templates — no Nuxt, no Vue, no React, no separate SPA.
- **Tailwind CSS v4.x** (latest stable, e.g. 4.3.x) for styling, via the official Vite/Tailwind integration Laravel ships with.
- **Alpine.js** for the light client-side interactivity Blade alone can't do: `MediaRecorder`-based mic capture, countdown timers, and swapping server-returned HTML/JSON into the page after each turn. No client-side state library (no Pinia/Redux) — state lives in the Laravel session/DB and is re-rendered server-side.
- **No charting library.** The Matter/Manner/Method + total score visualization on the feedback sheet is plain Tailwind (e.g. width-animated `<div>` bars driven by the score values the Blade view already has server-side). The Overview page's score trend is likewise drawn by hand — `App\Support\ScoreTrend` computes the coordinates and the view emits the SVG. This avoids pulling in Chart.js just to draw three bars and a total — one less dependency to wire up in a 6-hour build, and it stays consistent with the "no unnecessary JS" spirit of dropping the SPA framework in the first place.
- Noto Sans Bengali (or similar) web font for Bangla rendering.

**Backend**
- **Laravel 13.x** (latest stable, PHP 8.3+ required).
- Laravel's HTTP Client (Guzzle) for calling Gemini and ElevenLabs REST APIs — always from server-side controllers/services, never from the browser. **TLS verification is enabled** on every outbound request.
- SQLite for hackathon simplicity (swap to MySQL/Postgres post-hackathon).
- **Session-guard authentication** with a `users` table and a `debates.user_id` owner column. There is no Sanctum and no third-party auth provider — there is no separate API client, so the session guard is the whole of it. Authorization is a `DebatePolicy` applied as route middleware. A `session_id` column is still kept on `debates`: it identifies the browser that started the debate and is what the guest-adoption path matches on (see §6.10).
- File storage on the **private** disk for user recordings and cached TTS audio per turn (`storage/app/private/audio/`). The file name alone is stored on the turn row, and the audio is streamed back through `GET /debates/{debate}/audio/{file}`, which checks ownership — the public disk holds no private media.
- UI strings hardcoded in English; debate content language (en/bn) is data-driven per debate and threaded into every Gemini/ElevenLabs call — no i18n library, no `lang/` files (see §6.8, §17).

**Third-party APIs**
- Google Gemini API (via Google AI Studio) — multimodal (audio input), text generation, JSON-structured output for adjudication and rewrite suggestions.
- ElevenLabs API — multilingual text-to-speech, one voice ID per persona.

---

## 10. Data Model (Laravel schema)

All models use Laravel's `HasUuids` trait (auto-generated UUID primary keys — no manual ID assignment), except `users`, which uses Laravel's default auto-incrementing integer id. Child tables (`rounds`, `turns`, `turn_rewrites`, `adjudications`) declare **`ON DELETE CASCADE`** foreign keys, so deleting a debate removes its entire tree automatically (applied via migration `2026_08_17_000008_harden_schema_constraints` on existing SQLite DBs and in the base migrations for fresh installs).

```php
// users (Laravel's default auth table)
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

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
    $table->string('elevenlabs_voice_id_bn')->nullable();  // Bangla voice; falls back to the English one
    $table->timestamps();
});

// debates
Schema::create('debates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('session_id');            // the browser that started the debate
    $table->unsignedBigInteger('user_id')->nullable();  // the owning account; null = pre-account row
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
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
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

*Note: difficulty tiers are implemented as prompt-fragment constants in `config/debate.php` (see §14), not a DB table — this keeps the difficulty axis cheap to build (no admin UI needed) while still being fully data-driven for the persona side, which does benefit from DB-backed editable prompts. Tuning knobs also live in `config/debate.php`: `phase_duration` (180/120/120s), `ai_word_count` (keyed by phase **and slot** — Opening 150–220, replies 110–170, Rebuttal/Closing 100–160, the Closing's final reply 90–140), `debate_style` (the shared debate register), `stage_profiles` (the per-slot job and constraints), `side_labels` (the role each side speaks under), `phase_purposes`, `move_type_tags`, `rubric`, `tts_max_chars`, `words_per_minute` (140), `seed_session_id` and `seed_account_*`.*

*Note on `debates.user_id`: the column is **nullable**, so a debate created before accounts existed is never lost — `SeedAccountSeeder` adopts those orphans and gives them an owner. On SQLite the column is added by rebuilding the table with raw SQL, because Laravel's table rebuild silently drops the CHECK constraints and indexes the earlier `harden_schema_constraints` migration installed; the rebuild restores them explicitly.*

*Note on `turns.audio_path`: the column holds a bare **file name**, not a URL. The file lives on the private disk and is streamed back through the ownership-checked audio endpoint.*

---

## 11. Route / Endpoint Specification (Laravel, same-origin)

All of these are ordinary Laravel routes on the same app that serves the Blade pages — there is no separate API host, no CORS configuration, and no client-side API client library. Alpine.js components call these via `fetch()` (or plain form posts) and swap the returned HTML/JSON into the current page. Route parameters use implicit model binding (`/debates/{debate}`).

**Route names were deliberately left unchanged when the URLs moved** under `/dashboard` — only the URLs moved, so every `route()` call in the views and controllers kept working.

### 11a Public routes

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/` | Home / landing (Blade view) |
| `GET` | `/healthz` | Liveness check — app + database reachable; returns `{"status":"ok"}` or a 500 |
| `GET` | `/register` | Registration screen (Blade view) |
| `POST` | `/register` | Creates the account, signs in, adopts guest-session debates → redirects to the dashboard |
| `GET` | `/login` | Sign-in screen (Blade view) |
| `POST` | `/login` | Authenticates; regenerates the session ID; per-account lockout after 5 failures |
| `POST` | `/logout` | Signs out, invalidates the session, regenerates the CSRF token |

### 11b Authenticated routes (`auth` middleware)

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/dashboard/overview` | The Overview page: metric strip, score trend, rubric averages, reports by difficulty, the four most recent debates |
| `GET` | `/dashboard/settings` | Account settings: profile, password, account deletion |
| `PATCH` | `/dashboard/settings` | Update the profile (name, email) |
| `PATCH` | `/dashboard/settings/password` | Change the password (current password required) |
| `DELETE` | `/dashboard/settings` | Delete the account and every debate it owns |
| `GET` | `/dashboard/setup` | Setup screen: language, motion, side, persona, difficulty, mode (Blade view) |
| `POST` | `/motions/generate` | Body: `{ category?, language }` → returns `{ motion }` (Gemini, with offline fallback sample motions) |
| `POST` | `/debates` | Body: `{ motion_text or motion_id, user_side or "auto", persona_id, difficulty, mode, language }` → creates the debate (incl. seeded first round) owned by the signed-in account, redirects to `/debates/{id}` |
| `GET` | `/debates/{debate}` | Debate screen (Blade view) with full current state: motion, side, persona, difficulty, mode, all rounds/turns so far |
| `POST` | `/debates/{debate}/turns` | Multipart: audio blob → transcribes user speech (Scribe → Gemini audio fallback), generates the AI counter-turn from the stage brief, synthesizes the AI voice (ElevenLabs → Google TTS → silent); returns `{ user_transcript, user_audio_url, stt_error, ai_text, ai_audio_url, ai_error, round_complete, debate_complete, new_phase, next_speaker }` — partial results always included |
| `POST` | `/debates/{debate}/turns/{turn}/rewrite` | No body needed beyond the turn ID → returns `{ original_text, rewritten_text, explanation_bullets }` (cached per turn; only user turns) |
| `POST` | `/debates/{debate}/adjudicate` | Triggered after the final phase (or the user ends a Sparring round) → creates the adjudication row, marks the debate `adjudicated`, redirects to the feedback screen |
| `GET` | `/debates/{debate}/feedback` | Adjudication feedback sheet (Blade view) |
| `GET` | `/debates/{debate}/transcript` | Returns the full plain-text transcript as a downloadable `.txt` file |
| `GET` | `/debates/{debate}/audio/{file}` | Streams a stored recording from the **private** disk; checks ownership; `Cache-Control: private` |
| `GET` | `/dashboard/history` | History screen: the signed-in account's debates (Blade view) |
| `DELETE` | `/debates/{debate}` | Delete a debate (children removed via `ON DELETE CASCADE`), redirects back to history |

### 11c Middleware applied

| Route group | Middleware |
|---|---|
| `/register`, `/login` | `guest` — a signed-in visitor is redirected to the dashboard |
| `/logout` | `auth` |
| Every `/dashboard/*` route | `auth` |
| `/debates/{debate}` and its sub-routes | `auth` + `can:view,debate` / `can:participate,debate` / `can:adjudicate,debate` / `can:rewrite,debate` / `can:delete,debate` |
| `/motions/generate`, turn submission, rewrite, adjudication | `throttle:api` — 20/min and 300/hour per account, with a 60/min per-IP ceiling |
| `/register` | `throttle:register` — 5/min and 20/hour per IP |
| `/login` | `throttle:login` — 12/min per account+IP and 20/min per IP |

---

## 12. Third-Party Integration Details

### 12.1 Gemini API — prompt architecture
Five distinct prompt "modes," all going through the same Gemini endpoint but with different system instructions:

**A. Motion generation**
> "Generate one debatable, balanced motion in the style of '{category}' for a university debate. Phrase it as 'This House believes...'. Respond in {language}. Output only the motion, no preamble."

**The debate register (shared by B, B2 and C)**

The persona prompt says *who* the speaker is and the difficulty fragment says *how strong* they are. Neither says anything about the **register**, and a model with no register instruction writes in prose paragraphs with headings — which is not what a debater does at a podium, and which the TTS voice then reads aloud like a report being presented to a board. The register lives in `config('debate.debate_style')` and is appended to the system prompt:

> "You are speaking at the podium in a live one-on-one debate round. Your speech is spoken, not written: it must sound like a debater talking to a room, not like an essay being read out. Write in full sentences as they would be spoken aloud, and cut anything that a listener would have to read twice to follow."
>
> Its rules: signpost every part out loud, so a listener can follow the case without seeing it; every argument is a chain — claim, mechanism, impact; engage the opponent through what they actually said, never "the opposition" in the abstract; no headings, bullet points, numbered lists, markdown, stage directions or quotation marks around the speech; no filler and no throat-clearing, and no pleasantries ("Good morning", "Ladies and gentlemen") — open on the first substantive line; never break character, never mention being an AI, never narrate what you are about to do.

**B. Debate turn (opponent response)**
The prompt is built from a **stage brief** (`App\Services\DebateStage`) rather than from the phase name alone. A phase holds two speeches and the two are not the same speech, so the brief answers four questions: which phase this is and what the phase is FOR in the round; which **slot** within the phase the speaker holds (first or second); which speech they are **answering**, verbatim; and what their own side has already established.

System prompt assembled from: persona's `system_prompt` (style) + the **debate register** (the shared rules that make the output a spoken speech rather than an essay) + a **language directive** + the difficulty tier's prompt fragment (skill level, see §14):
> "You are debating the motion: '{motion}'. You are arguing the {ai_side} side as {side_role}. {stage_brief}. Respond as your persona would, at your assigned skill level — in spoken debate register, {word_count_range} words, in {language}. Directly engage with the user's most recent point where relevant. Do not break character or mention you are an AI."
>
> The **language directive** states plainly that the instructions are in English and only the speech is not, and that instruction text must not leak into the speech. Without it, a model told to write a Bangla speech by English instructions translates the instruction labels mid-sentence — which is how the side labels came out of the speech as "the esteemed Prime Minister".
>
> The **stage brief** carries a slot-specific job from `config('debate.stage_profiles')`, keyed `"{phase}.{first|second}"` — because the phase alone does not determine the speech:
>
> | Slot | Role | Job |
> |---|---|---|
> | `opening.first` | Opening Speech | Build the case from the ground up: framework, definition/scoping, 2–3 developed arguments (claim → mechanism → impact). No rebuttal — nothing has been said yet. |
> | `opening.second` | Reply to the Opening | Answer the opponent's opening argument by argument — extend, undercut or rebut each — then re-anchor your own case. Not a second opening. |
> | `rebuttal.first` | Rebuttal Speech | Take the point they have not yet answered and finish it; press the weakness in any answer they have given. |
> | `rebuttal.second` | Final Rebuttal | Answer their rebuttal point by point, resolve the clash, and leave the closing its ground. |
> | `closing.first` | Closing Speech | The Opposition's reply speech: answer the Government's rebuttal point by point and set up the one clash the Government's final reply must answer. It does not weigh the round and does not declare a winner. |
> | `closing.second` | Final Reply | The Government's last word: weigh briefly, defeat the strongest claim in the Opposition's closing, and declare the outcome. |
>
> The brief also carries an **authority** line stating whether this speech decides the round. The WSDC / Asian Parliamentary 1v1 format gives the last word to one speaker only — in the Closing phase the Opposition replies first and the Government delivers the final word — and the model needs that stated as a fact of the slot, not as advice in the constraint list. It was originally only in the constraints ("do not announce a verdict"), and the first closing speaker kept ending with its own victory: the closing phase was described as *"the phase in which the round is weighed and a verdict is reached"*, and that description went to both closing speeches, so the Opposition, speaking first, read it as a licence to reach the verdict. The phase no longer claims a verdict is reached in it, and `authority` states per slot: `closing.second` decides the round and may declare the outcome in its own voice; `closing.first` and every earlier speech do not decide it and must not announce a winner.
>
> The brief also states which phases are already argued and which are still to come, so a speech does not spend material belonging to a later phase — a closing introduces no new arguments, and a reply does not rebuild a case already made. The speech being answered is quoted verbatim and labelled by the phase it was SPOKEN in, not the phase the speaker is standing in (the first rebuttal answers the *opening* speech). In Sparring Mode the brief instead asks the model to pick one move type — follow-up question, contradiction callout, live challenge, or standard rebuttal — and open with `[FOLLOW-UP]` / `[CONTRADICTION]` / `[CHALLENGE]` / `[REBUTTAL]`; the tag is parsed out and stored in `turns.ai_move_type`.
>
> `{word_count_range}` is keyed by phase **and slot** (config-driven in `config/debate.php`): the second speaker of a phase is never delivering the same speech as the first, so a reply is not given an opening's word budget — **Opening ~150–220 words, replies ~110–170, Rebuttal/Closing ~100–160**, with the Closing's final reply shortest at ~90–140.

Input includes the full prior transcript (all turns so far, each labeled with its phase via `Debate::buildTranscript()` — e.g. `[OPENING PHASE] Government: ...`). The speakers are labelled by the team they speak for (`config('debate.side_labels')`) rather than as "You" and "AI Opponent": a model that reads "You (government)" in a transcript it was told it was writing has to re-derive, on every turn, which of the two voices "You" is.

**B2. Audio fallback (Gemini multimodal — single-call transcribe + respond)**
> "The following audio is from a student speaking in a debate on the motion: '{motion}'... First, transcribe exactly what the student said... Then, as the opponent ({ai_side} side), generate your debate response... Output ONLY valid JSON: { transcript, ai_text }"

Used only when ElevenLabs Scribe STT fails or returns near-empty text (§6.4). The raw audio (base64, `audio/webm`) is sent inline carrying **the same stage brief** as the text path — phase, slot, the speech being answered and the debate register; Gemini returns both the user's exact words and the AI's response in one multimodal call, so the round loses no latency and the AI turn can still be generated. The call also carries the same `system_instruction` as the text path (persona, register, language directive, difficulty), so a fallback does not produce a phase-blind, register-blind opponent.

**C. Argument rewrite suggestion**
> "The following is one turn from a student practicing debate on the motion '{motion}'. Rewrite it to be a meaningfully stronger version of the *same* argument — same core position, sharper structure, better evidence or examples, clearer logic. Do not change what side they're arguing. Respond in {language}. Output ONLY valid JSON matching this schema: { rewritten_text: string, explanation_bullets: [string] } — explanation_bullets should be 2–3 short phrases naming what changed."

The prompt is **slot-aware** (see §6.4a): it names the phase *and* the slot the turn was delivered in, and re-targets the rewrite accordingly (Opening → strengthen framework/constructive case; Rebuttal → sharper surgical clash; Closing → impact comparison and persuasive framing; the second speaker of a phase → answer the speech it answers rather than opening fresh). A reply stays a reply; an opening stays an opening.

**D. Adjudication** — must request strict JSON output:
> "You are an experienced debate adjudicator scoring the {user_side} speaker on the motion '{motion}'. Score Matter (/40), Manner (/30), Method (/30). Identify any logical fallacies used by the user speaker, citing the phase. Give 3–5 concise, actionable feedback bullets that quote the exact user phrase from the transcript and explain what to improve. Include a final bullet prescribing one specific daily practice drill. Give a one-line verdict. Respond in {language}. Output ONLY valid JSON matching this schema: { matter_score, manner_score, method_score, total_score, fallacies: [{phase, type, explanation}], feedback_bullets: [string], verdict: string }"

Laravel parses this JSON directly into the `adjudications` / `turn_rewrites` tables; a `parseJson` repair helper strips stray markdown code fences, and on decode failure it falls back to defaults (logged) rather than crashing the request.

### 12.2 ElevenLabs — voice mapping & TTS pipeline
- Each `persona` row stores **two** voice IDs — `elevenlabs_voice_id` (English) and `elevenlabs_voice_id_bn` (Bangla, nullable) — selected ahead of time from the team's ElevenLabs voice library to match the character described in §6.2. Difficulty does not affect voice selection.
  - Calm Logician → Adam `pNInz6obpgDQGcFmaJgB` / Fahad Mir `d532cgnzlrx7AWL3CD9i`
  - Aggressive Cross-Examiner → Bella `EXAVITQu4vr4xnSDxMaL` / Rayhan `vvOYlSJHf8lTWIG4tDNP`
  - Devil's Advocate → Liam `TX3LPaxmHKxFdv7VOQHJ` / Rakib `VizHiwkPO7hXc967v4tE`
- The Bangla voices are native Bangla recordings, so a Bangla debate is spoken by someone who actually speaks Bangla rather than by an English voice reading Bangla aloud. The debate's `language` column selects the voice; a persona with no Bangla voice falls back to its English one, so synthesis never fails on a missing voice.
- Model is chosen per language: `eleven_multilingual_v2` for Bangla (best Bangla phonetics with any voice), `eleven_turbo_v2_5` for English.
- Laravel calls ElevenLabs' text-to-speech endpoint with the AI's generated text (pre-trimmed to `tts_max_chars` at a sentence boundary; retried with a credit-budget-trimmed text on `quota_exceeded`) and stores the returned audio on the **private** disk under `storage/app/private/audio/`, returning a bare file name. The Blade page streams it back through the ownership-checked audio endpoint.
- The user's own recording is written to the same private disk by the turn endpoint. `turns.audio_path` holds the **bare file name**, not a URL, and the file is reduced to a basename and matched against a strict pattern before it is read — so the endpoint cannot be used to traverse outside the audio directory.
- **STT:** ElevenLabs Scribe (`scribe_v1`) transcribes the user's audio; `language_code` is pinned for en/bn.

### 12.3 Resilience & Fallback Chain (applies to every third-party call)
1. **Motion generation:** Gemini fails → returns a random motion from curated per-language fallback lists (`en`, `bn`).
2. **STT:** Scribe fails/empty → Gemini multimodal audio (transcribe + respond, single call) → still failing, the user's turn is saved with an empty transcript (audio kept) and the UI shows a clear note; AI still responds; round continues.
3. **TTS:** ElevenLabs fails (or quota can't fit) → Google Translate TTS (free endpoint, pure PHP, chunked at ~190 chars) → silent text-only fallback with a visible notice; the AI's text is always shown regardless.
4. **Gemini text generation:** transient errors are retried automatically (up to 5 retries with backoff on 429/5xx); persistent failure returns a friendly, human-readable error message to the page — never a raw exception — and the round state stays intact for retry.
5. **Turns are persisted independently:** user turn, AI text, AI audio, phase advancement, and the JSON response each succeed or fail on their own; whatever succeeded is returned to the client (`stt_error` / `ai_error` fields carry the rest).

**Cost control.** The Gemini and ElevenLabs endpoints cost money per call, so they are capped **per account** rather than per IP — one abusive account must not be able to exhaust the shared quota. The limiters are registered in `AppServiceProvider`:

| Limiter | Limits | Applies to |
|---|---|---|
| `api` | 20/min and 300/hour per account, 60/min per IP | Motion generation, turn submission, rewrite, adjudication |
| `login` | 12/min per account+IP, 20/min per IP | Sign-in |
| `register` | 5/min and 20/hour per IP | Registration |

Sign-in additionally carries a **per-account lockout** in the cache — five failed attempts lock that account for 15 minutes, and a successful sign-in clears the counter. The lockout is counted per account so one attacker cannot lock every account out by hammering a single address; the route throttle is the backstop against a spray across many addresses from one host.

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

**Side roles (`config('debate.side_labels')`)** — the sides are the Government team and the Opposition team (সরকার দল / বিরোধী দল), the same terms the setup screen offers the player and the terms a debate competition in Bangladesh uses. They were originally the offices those teams hold in parliament — the Prime Minister and the Leader of the Opposition — which borrowed the British parliamentary model and was wrong twice over: it put a political office into a debate competition that has no business having one, and it made the AI open its Bangla speeches by addressing the other side as "the esteemed Prime Minister", a formal address no debater at a podium uses. Naming the sides still gives the AI a stance to speak from, and still gives the transcript the shape of a debate rather than a chat log. Each side carries a `role` (how the speaker names itself: "the Government team"), a `first` form ("the Government team"), a `short` form for the transcript ("Government"), a `self` form ("our side") and an `opponent` form used to name the other side inside a speech ("the Opposition team"). The labels are per language (`side_labels.en.*`, `side_labels.bn.*`), because a label is carried into the speech the model writes: a Bangla debate briefed in English forces the model to translate a label mid-sentence, and the translation lands in the speech as an address. The transcript therefore reads `[OPENING PHASE] Government: ...` rather than `You (government): ...` — a model told it is writing one of the two voices in a transcript it is also reading would otherwise have to re-derive, on every turn, which voice "You" is.

**No offices in the speech** — the prompt tells the model to refer to the sides and never to the speakers, and the register rules carry the same rule. A speech at a podium argues with a side's case, not with a person: "the government's claim", not "the Prime Minister's point".

---

## 15. Adjudication Rubric

| Category | Weight | What it measures |
|---|---|---|
| **Matter** | 40 pts | Quality, relevance, and logical soundness of arguments and evidence used |
| **Manner** | 30 pts | Persuasiveness, clarity, and rhetorical delivery (inferred from language used in the transcript for MVP; full prosody/tone-of-voice analysis is a stretch goal) |
| **Method** | 30 pts | Structure, organization, time use, and how directly the speaker engaged with and rebutted the opponent |

Fallacy detection covers common types relevant to debate (e.g. strawman, ad hominem, false dichotomy, slippery slope, appeal to emotion) — each instance tagged with the phase it occurred in and a short explanation, not a lecture.

Use of the rewrite assistant (§6.4a) during the round does not affect scoring — it's scored on what the user actually said, not on rewritten versions they viewed but didn't (and can't) resubmit.

**One rubric, not two.** `config('debate.rubric')` holds the three criteria, their weights, their band descriptors and the overall grade bands, and it is the single source of truth for both sides of the rubric. `GeminiService::rubricPromptText()` writes that config into the adjudication prompt — the judge is told where each band starts, so it does not invent its own thresholds — and the report (`App\Support\ReportAnalysis`) renders the same bands. A report that describes a rubric the judge never scored against is a document that misrepresents its own method.

---

## 16. UI/UX Screens

1. **Home / Landing** — hero headline, one-line pitch, stats row (3 phases / 3 AI personas / 100-point rubric / 2 languages), "How it works" four-step grid, "Begin a Debate Session" CTA. Public, and it keeps the horizontal nav rather than the workspace rail — a rail on a marketing page would be a false affordance. A signed-in visitor's nav carries one button (back to the dashboard); the other destinations are in the rail on every working page.
2. **Sign in / Register screens** — standalone, full-height, with their own brand treatment and no site nav. A live password-policy meter shows the requirements as you type; the sign-in screen carries a "Try the demo account" panel that fills the seeded credentials.
3. **Overview** (`/dashboard/overview`) — the landing page after sign-in, and a report rather than a launcher. It opens with a metric strip, then three analytics — the **score trend**, **rubric averages** and **reports by difficulty** — then the four most recent debates. A brand-new account is never a blank screen: it gets a "Start Your First Debate" call to action. Nothing is shown twice: the four recent debates are the only list of sessions on the page, and an unscored session is labelled *In progress* rather than given a zero.
4. **Setup screen** — language cards (English / বাংলা — "Affects audio, transcription & AI responses"), motion input/generate (with category dropdown), side picker (Government / Opposition / Surprise me), persona card grid (clean border highlight selection without checkmarks), difficulty selector (Beginner → World Champion, each with a short descriptor), mode toggle (Tournament / Sparring) with a live **standards note** under it explaining the format, submit CTA.
5. **Debate screen** — motion banner (persistent), phase indicator (Opening/Rebuttal/Closing with progress dots in Tournament Mode; a simple turn counter in Sparring Mode), timer (Tournament Mode, reset to the current phase's own budget on every phase change), big mic button, live transcript area (user + AI), an **"Improve my argument"** button next to each of the user's own turns that opens the before/after rewrite panel, "opponent is thinking" loading state, auto-playing AI audio with a replay button, status bubbles that are cleared between turns. Two panes with a switcher below 900px; the mic docks at the foot of the screen on a phone, with the phase clock beside it. Attribution (the logic model, the transcriber, the voice) lives once, in a single *AI Stack* panel in the podium. The opponent's speech is placed into the bubble in one go rather than typed out, so the reader can read the argument while it is being spoken.
6. **Adjudication / Feedback sheet** — the four-line brief at the top, then the scorecard, the grade ladder and the per-criterion ladders, the findings grouped by kind and anchored to a phase, the fallacies, the verdict, the delivery analysis against each phase's time budget, a transcript download action, and a printable/PDF export.
7. **History screen** (`/dashboard/history`) — list of the account's past debates, each showing motion, persona, difficulty, mode, language, status, and score when adjudicated; format tabs (Tournament / Sparring) that open on the format the reader has records for; search by motion text; filter by status; sort by newest, oldest, highest or lowest score; reopen a debate, download its transcript, or delete the session (cascading).
8. **Account settings** (`/dashboard/settings`) — the account stated once (name, email, and when it was opened), then the four things that can be changed about it: name, email, password, and the account itself. Deliberately carries **no statistics** — the Overview owns the full stat display.
9. **Global UI Components** — the workspace nav rail (new debate, the four pages, sign-out), a global confirmation modal centered in the middle of the viewport, and a toast notification container fixed in the bottom-right corner across all views.

Use the `frontend-design` conventions for visual polish (typography, spacing, avoiding generic templated look) when this is actually built. Everything here is a Blade view — no separate component library or client router.

---

## 17. Localization Strategy

- **Two Core Supported Languages:** The platform supports **English (`en`)** and **Bangla (`bn`)** debate content modes (validated via `SetupController` and enforced via `debates.language IN ('en', 'bn')` CHECK constraint).
- **UI strings are hardcoded in English** in Blade views. Full i18n (Laravel `lang/*.php` + `__()` everywhere) was descoped: the product's "language" is the debate's *content* language, and every user-facing flow works in English with Bangla debate content.
- Every backend call that touches Gemini/ElevenLabs takes an explicit `language` parameter driven by the debate's `language` column (`en` / `bn`) — never auto-detected from speech, to avoid inconsistent mid-debate language switches.
- Bangla rendering is verified across all screens via the Noto Sans Bengali web font (Inter + Noto Sans Bengali loaded from Google Fonts in the layout), including the feedback sheet's score labels.
- Natural Bangla/English code-switching within a single spoken turn (common among Bangladeshi debaters) is handled natively when Gemini's multimodal audio fallback transcribes it (see §12.1).
- **The derived analysis prose is English in both languages.** The report's brief, the grade ladder's band summaries, the phase notes and the delivery commentary are derived in PHP rather than generated by Gemini, so they are written in English. A Bangla report therefore has Bangla *judging* inside English *scaffolding* — the findings, the mark and the transcript are Bangla; the four-line brief around them is not. Localising that scaffolding is the obvious next step, and it is a matter of translating a fixed set of strings rather than of changing any logic.
- **Bangla word counts are a heuristic.** Bangla does not space its words, so the report counts characters and divides by five (the average Bangla word length). It is close enough to be useful but it is an estimate, and it is the input to the delivery analysis.
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

**Shipped after the hackathon build** (see §6.10 for the full specification):

- **Accounts** — registration, sign-in, sign-out, per-account brute-force lockout, password policy, email normalisation, and guest-session adoption.
- **Ownership and authorisation** — `DebatePolicy` as the single authority on every debate route; per-account rate limiting on the AI-backed endpoints; the seeded demo debate owned by the demo account alone.
- **The Overview page** — metric strip, score-trend chart, rubric averages, reports by difficulty, recent debates.
- **The report analysis engine** (`App\Support\ReportAnalysis`) — the four-line brief, the grade ladder, the per-criterion ladders, phase-anchored findings, verified quotes, delivery analysis, and Bangla-aware word counts.
- **The stage brief** (`App\Services\DebateStage`) — the phase-**and-slot**-aware prompt architecture described in §12.1 B, plus the shared debate register, the side role labels, and the slot-aware rewrite.
- **Native Bangla voices** — each persona carries a second, native Bangla voice ID.
- **Private audio** — voice recordings moved off the public disk onto the private one, streamed back through an ownership-checked endpoint; outbound TLS verification re-enabled.
- **The workspace shell** — the nav rail, the credential screens, the account settings page, and a rebuilt dark design system in `resources/css/app.css`.

---

## 19. Post-Hackathon Roadmap (not for the event submission)

- Full Sparring Mode refinement (better contradiction detection across a longer transcript, tunable aggressiveness).
- Points of Information (POIs) — AI can interject briefly during user speeches.
- Full 4-speaker team format (multi-agent, one Gemini persona per bench position).
- **Deeper practice analytics.** Accounts, account-scoped history and a first cut of progress tracking over time shipped in §6.10. Still open: tracking whether a user's later arguments start to resemble past rewrite suggestions (a genuine "did you learn from this" signal), and comparing a user's own delivery across the six slots rather than only the total mark.
- Prosody-aware Manner scoring (analyzing actual vocal delivery, not just transcript).
- Tournament-style ladder against increasingly difficult AI personas (natural extension of the difficulty axis).
- Localising the report's derived prose — the brief, the band summaries, the phase notes and the delivery commentary are English in a Bangla report. The judge's own findings, the mark and the transcript are already Bangla; only the scaffolding around them is not. It is a matter of translating a fixed set of strings, not of changing any logic.

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
| Gemini/ElevenLabs latency breaks the "live sparring" feel | Clear "opponent is thinking" state; keep AI responses short (90–220 words, keyed by phase **and slot** — see §12.1) to reduce TTS generation time |
| Rewrite-suggestion calls add a second latency point mid-round | Keep the rewrite prompt short and single-purpose (no TTS involved), target <5s; make it fully optional/on-demand so it never blocks the round's critical path; results are cached per turn |
| Bangla speech transcription accuracy lower than English | Rely on Scribe (pinned Bangla language code) with Gemini multimodal audio as an automatic fallback; the user's recording is always saved for review even when transcription fails |
| ElevenLabs outage / API key expiry / quota exhaustion mid-demo | Full TTS fallback chain: quota-aware text trimming + retry → free Google Translate TTS (pure PHP, no key) → silent text-only playback with a visible notice. A demo never runs fully silent |
| Adjudication or rewrite JSON malformed | Backend-side JSON repair step (strips markdown fences); strict schema instruction in the prompt for both; graceful defaults on decode failure |
| Live demo mic/audio failure in front of judges | Always have a pre-recorded 3-min demo video as backup, per submission requirements; plus the in-app "we didn't catch that" retry state |
| Sparring Mode's added branching logic eats into build time | Explicitly P1/P2 (§18) — Tournament Mode ships first and stands alone as a complete, demoable product even if Sparring Mode doesn't make it in |
| Scope too large for 6 hours | P0/P1/P2 triage in §18; a working English-only, one-persona, Beginner-difficulty-only, no-Sparring-Mode version (with the rewrite assistant intact, since it's the standout demo moment) is the true minimum fallback |
| A shared API quota is exhausted by one account | The AI-backed endpoints are rate-limited **per account** (20/min, 300/hour) with a per-IP ceiling, not per IP alone — one abusive account cannot exhaust the shared Gemini/ElevenLabs quota. TTS degrades through the quota-trim retry to the free Google Translate endpoint before it ever goes silent (§12.3) |
| A voice recording leaks to another account | Recordings live on the **private** disk, are served only through an ownership-checked route, are reduced to a bare filename matched against a strict pattern, and are returned with `Cache-Control: private` (§12.2) |
| The account model makes a debate unreachable | `debates.user_id` is nullable, and the `SeedAccountSeeder` adopts every pre-account orphan so no debate is ever orphaned by the ownership change (§10) |

---

## 22. Appendix A: System Prompt Fragments (shipped — see `PersonaSeeder` and `config/debate.php`)

**Calm Logician (persona system_prompt, voice `pNInz6obpgDQGcFmaJgB` / Bangla `d532cgnzlrx7AWL3CD9i`):**
> "You debate in a calm, analytical, and structured manner. Frame your response with clear premise-conclusion structures. Rely on empirical evidence, logical deduction, and structured points (e.g. 'First... Second...'). Never raise your tone or sound flustered. Maintain an unshakeable academic composure."

**Aggressive Cross-Examiner (voice `EXAVITQu4vr4xnSDxMaL` / Bangla `vvOYlSJHf8lTWIG4tDNP`):**
> "You debate assertively and directly. Open by challenging the weakest part of your opponent's last point. Use short, punchy sentences. Do not soften your language, but stay respectful and never personal. Your pace is fast and your tone is high-energy — you are here to win, and you show it."

**Devil's Advocate (voice `TX3LPaxmHKxFdv7VOQHJ` / Bangla `VizHiwkPO7hXc967v4tE`):**
> "You deliberately argue the least comfortable, most contrarian defensible position. Use a wry, ironic tone. Point out inconvenient implications of your opponent's stance. You enjoy intellectual provocation — not to be annoying, but to expose the assumptions everyone is quietly ignoring."

**Difficulty fragments (all four, from `config/debate.php`, appended to whichever persona is selected):**
- **Beginner:** "Argue simply and clearly, as a friendly practice partner would. Let minor weaknesses in your opponent's case go unchallenged so they can build confidence. Use straightforward vocabulary and avoid complex rhetorical moves."
- **Intermediate:** "Argue at a solid club-level standard. Engage directly with your opponent's main points, use one or two concrete examples, and maintain a clear logical structure throughout your speech."
- **Advanced:** "Argue at a competitive tournament standard. Use precise evidence and real-world examples. Identify and target the weakest premise in your opponent's case. Employ sophisticated rhetorical structures (e.g. link-turns, impact calculus). Leave no major claim by your opponent unaddressed."
- **World Champion:** "Argue at a tournament-champion skill level. Use precise evidence and examples. Actively identify the single weakest link in your opponent's most recent point and target it directly. Employ advanced debate techniques: pre-empting likely responses, framing the round's core clash, and making explicit impact comparisons. Your arguments should be airtight."

**The debate register** (from `config('debate.debate_style')`, appended to every debate-turn and rewrite prompt — see §12.1):
> "You are speaking at the podium in a live one-on-one debate round. Your speech is spoken, not written: it must sound like a debater talking to a room, not like an essay being read out. Write in full sentences as they would be spoken aloud, and cut anything that a listener would have to read twice to follow."

Its seven rules: signpost every part out loud; every argument is claim → mechanism → impact; engage the opponent through what they actually said; no headings, bullets, numbered lists, markdown, stage directions or quotation marks around the speech; no filler, no throat-clearing, no pleasantries; never announce the phase you are in ("Welcome to the closing phase", "In my rebuttal", "Today we evaluated whether") — open on the first substantive line; never break character, never mention being an AI, never narrate what you are about to do.

The phase-announcement ban exists because the prompt necessarily tells the model which phase and slot it is in, and without an explicit ban the model reads that as something to say out loud. A generic "no pleasantries" rule does not cover it — the announcement is not a pleasantry, it is a narration of the speech's own metadata.

**The stage profiles** (from `config('debate.stage_profiles')`, keyed `"{phase}.{first|second}"` — the full table is in §12.1 B). Each profile names the role the speech plays, the job it has to do, and the constraints that keep it inside the speech it is meant to be. The slot key is read as a **flat array key**, not through Laravel's dot-notation lookup — `config('debate.stage_profiles.opening.first')` would resolve to the `first` key *inside* `opening`, and every profile would silently fall back to `sparring`.

## 23. Appendix B: Sample Motions

- This House believes social media does more harm than good.
- This House would ban private tutoring (coaching centers).
- This House believes AI-generated art should be eligible for copyright.
- This House would prioritize climate migrants in refugee policy.
- This House believes university admissions should be need-blind.

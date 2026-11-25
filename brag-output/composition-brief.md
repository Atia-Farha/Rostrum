# Hyperframes Composition Brief: Rostrum

## Objective
Create a ~90-second demo/launch video for Rostrum — an AI Debate Training Platform.

## Output
- Composition directory: `brag-output-2026-11-25-213641/composition/`
- Format: landscape — 1920×1080
- Duration: ~90 seconds

## Source Material
- Project root: /home/atia-farha/Documents/Projects/Laravel-Projects/Rostrum
- Primary files read: home.blade.php, debate.blade.php, setup.blade.php, dashboard.blade.php, feedback.blade.php, app.css, layouts/app.blade.php, README.md, dev.md
- Product name: Rostrum
- Tagline: "Debate Sharper. Score Higher."
- Key UI moments to recreate:
  - 6-step debate setup form with Gemini motion generation
  - Split-pane debate arena with mic orb, transcript, podium, timer
  - Argument rewrite panel with before/after and coaching bullets
  - Adjudication report with verdict, scorecard, findings, fallacy detection
  - Dashboard with score trend chart, rubric averages, difficulty breakdown
- Copy that must appear verbatim:
  - "AI Debate Training Platform"
  - "Debate Sharper. Score Higher."
  - "Official Adjudication Report"
  - "Powered by Gemini 3.6 Flash & ElevenLabs Scribe & Voice"
  - "Matter" / "Manner" / "Method"

## Creative Direction
- Tone preset: polished
- Creative direction: serious academic tool — confident, clean, purposeful
- Interpretation: Measured pacing with clean transitions. The product is earnest and substantial. No hype, no jokes. Let the product demonstrate itself.
- Angle: This is a full competitive debate arena that fits in your browser — not a chatbot. The video walks through the problem, the users, the solution, every major feature with working UI, and transparently identifies limitations.
- Hook: "Who coaches you between tournaments?" — the problem every competitive debater knows
- Outro: "Debate Sharper. Score Higher." with Rostrum branding and credits
- Avoid:
  - Generic SaaS language ("streamline your workflow")
  - Abstract filler visuals
  - Overselling — be honest about limitations

## Visual Identity
- Background: `#090b10`
- Text: `#f8fafc`
- Accent: `#4f5edd` (indigo)
- Accent 2: `#38bdf8` (sky blue)
- Display font: Inter 600-800
- Body font: Inter 400-500 + Noto Sans Bengali for Bangla
- Visual references: dark glassmorphism UI, indigo accent with glow effects, numbered section markers, subtle borders

## Storyboard
Use the storyboard in `brag-output-2026-11-25-213641/brag-plan.md` as the creative contract.

Scene summary:
1. Hook: The Problem — 8s — Bold question + 3 pain points
2. Solution & Users — 6s — Branding + target audiences
3. Setup Flow — 10s — 6-step form recreation
4. Live Debate Arena — 15s — Split-pane workspace with live interaction simulation
5. Argument Rewrite — 8s — Before/after with coaching
6. Adjudication Report — 15s — Full report with scores, findings, fallacies
7. Dashboard & Analytics — 8s — Metric strip, charts, trends
8. Bilingual Support — 5s — English/Bangla side-by-side
9. Architecture — 5s — Flow diagram + tech stack
10. Known Limitations — 5s — Honest callout of incomplete features
11. Outro — 5s — Branding, credits, CTA

## Audio
- Audio role: intentional silence — demo/presentation video
- Music: none
- SFX: none

## Implementation
- Self-contained HTML file with inline CSS/JS
- Auto-playing animation through all scenes
- CSS animations and JS timing for transitions
- Screen-recordable at 1920×1080
- Progress bar at bottom showing overall video progress

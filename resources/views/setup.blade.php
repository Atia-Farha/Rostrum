@extends('layouts.app')

@section('title', 'Configure Debate Session — Rostrum')

@section('content')

<style>
    .setup-page {
        max-width: 780px;
        margin: 0 auto;
        padding: 2.5rem 1.25rem 5rem;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* Ambient glow & container styling */
    .setup-header {
        margin-bottom: 2.25rem;
    }

    .setup-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        color: #7b91b3;
        text-decoration: none;
        margin-bottom: 1.5rem;
        transition: color 0.15s ease, transform 0.15s ease;
    }

    .setup-back:hover { 
        color: #6675f5; 
        transform: translateX(-2px);
    }

    .setup-title {
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 700;
        color: #f0f4ff;
        letter-spacing: -0.025em;
        margin: 0 0 0.4rem;
    }

    .setup-subtitle {
        font-size: 0.88rem;
        color: #7b91b3;
        font-weight: 400;
        line-height: 1.6;
        margin: 0;
    }

    /* Section block */
    .setup-block {
        background: #0a0d16;
        border: 1px solid #1e2535;
        border-radius: var(--radius-lg, 4px);
        padding: clamp(1.25rem, 3vw, 1.75rem);
        margin-bottom: 1.25rem;
        transition: border-color 0.25s ease, box-shadow 0.25s ease;
    }

    .setup-block:hover {
        border-color: rgba(99, 102, 241, 0.3);
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
    }

    .setup-block-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid #111827;
        flex-wrap: wrap;
    }

    .setup-step-num {
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

    .setup-step-label {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #c8d5f0;
    }

    .setup-step-hint {
        font-size: 0.72rem;
        color: #7b91b3;
        font-weight: 500;
        margin-left: auto;
    }

    /* Motion textarea */
    .setup-textarea {
        width: 100%;
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        color: #f0f4ff;
        font-size: 0.925rem;
        line-height: 1.6;
        padding: 0.875rem 1rem;
        resize: vertical;
        font-family: 'Noto Sans Bengali', 'Inter', system-ui, sans-serif;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        box-sizing: border-box;
    }

    .setup-textarea::placeholder { color: #687b99; }
    .setup-textarea:focus { 
        outline: none; 
        border-color: #6675f5;
        box-shadow: 0 0 12px rgba(102, 117, 245, 0.2);
    }

    /* Generate row */
    .setup-generate-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.85rem;
        flex-wrap: wrap;
    }

    .setup-or {
        font-size: 0.75rem;
        color: #7b91b3;
        font-weight: 600;
    }

    .setup-select {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        color: #c8d5f0;
        font-size: 0.825rem;
        padding: 0.55rem 0.75rem;
        flex: 1;
        min-width: 140px;
        max-width: 220px;
        transition: border-color 0.15s ease;
    }

    .setup-select:focus { 
        outline: none; 
        border-color: #6675f5; 
    }

    .setup-gen-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.55rem 1.1rem;
        border-radius: 4px;
        background: rgba(102, 117, 245, 0.12);
        border: 1px solid rgba(102, 117, 245, 0.3);
        color: #818cf8;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .setup-gen-btn:hover:not(:disabled) {
        background: rgba(102, 117, 245, 0.22);
        border-color: rgba(102, 117, 245, 0.5);
        color: #f0f4ff;
    }

    .setup-gen-btn:disabled { opacity: 0.45; cursor: not-allowed; }

    .setup-generated-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #34d399;
        background: rgba(16,185,129,0.08);
        border: 1px solid rgba(16,185,129,0.2);
        padding: 0.25rem 0.6rem;
        border-radius: 3px;
        margin-top: 0.5rem;
    }

    .setup-gen-error {
        margin-top: 0.6rem;
        font-size: 0.78rem;
        font-weight: 500;
        color: #fca5a5;
        background: rgba(239,68,68,0.08);
        border: 1px solid rgba(239,68,68,0.25);
        padding: 0.5rem 0.75rem;
        border-radius: 4px;
        line-height: 1.5;
    }

    /* Option cards (language, side, mode) */
    .setup-option-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }

    .setup-option-card {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        padding: 1rem 1.15rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        user-select: none;
    }

    .setup-option-card input[type="radio"],
    .setup-persona-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: -1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
    }

    .setup-option-card:hover {
        border-color: rgba(99, 102, 241, 0.4);
        background: rgba(99, 102, 241, 0.04);
    }

    .setup-option-card.selected {
        background: rgba(79, 94, 221, 0.1);
        border-color: #6675f5;
        box-shadow: 0 0 16px rgba(102, 117, 245, 0.15);
    }

    .setup-option-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 0.3rem;
        transition: color 0.15s ease;
    }

    .setup-option-card.selected .setup-option-title { color: #f0f4ff; }

    .setup-option-desc {
        font-size: 0.75rem;
        color: #7b91b3;
        line-height: 1.5;
    }

    .setup-option-card.selected .setup-option-desc { color: #94a3b8; }

    .setup-standard-note {
        margin-top: 0.75rem;
        padding: 0.75rem 1rem;
        background: rgba(79, 94, 221, 0.06);
        border: 1px solid rgba(79, 94, 221, 0.2);
        border-radius: 4px;
        font-size: 0.76rem;
        line-height: 1.6;
        color: #94a3b8;
    }

    .setup-standard-note strong { color: #818cf8; font-weight: 700; }

    /* Persona grid */
    .setup-persona-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }

    .setup-persona-card {
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        padding: 1.1rem 1.15rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        user-select: none;
    }

    .setup-persona-card:hover {
        border-color: rgba(99, 102, 241, 0.4);
        background: rgba(99, 102, 241, 0.04);
    }

    .setup-persona-card.selected {
        background: rgba(79, 94, 221, 0.1);
        border-color: #6675f5;
        box-shadow: 0 0 16px rgba(102, 117, 245, 0.15);
    }

    .setup-persona-name {
        font-size: 0.9rem;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 0.4rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .setup-persona-card.selected .setup-persona-name { color: #f0f4ff; }

    .setup-persona-check {
        width: 16px;
        height: 16px;
        color: #6675f5;
        flex-shrink: 0;
    }

    .setup-persona-desc {
        font-size: 0.78rem;
        color: #7b91b3;
        line-height: 1.55;
    }

    .setup-persona-card.selected .setup-persona-desc { color: #94a3b8; }

    /* Difficulty tabs */
    .setup-diff-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        background: #06080f;
        border: 1px solid #1e2535;
        border-radius: 4px;
        overflow: hidden;
    }

    .setup-diff-tab {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.7rem 0.5rem;
        color: #7b91b3;
        background: none;
        border: none;
        border-right: 1px solid #1e2535;
        cursor: pointer;
        transition: all 0.15s ease;
        text-align: center;
        white-space: nowrap;
    }

    .setup-diff-tab:last-child { border-right: none; }
    .setup-diff-tab:hover { background: rgba(99, 102, 241, 0.08); color: #818cf8; }
    .setup-diff-tab.active { background: #4f5edd; color: #fff; }

    .setup-diff-hint {
        margin-top: 0.75rem;
        font-size: 0.8rem;
        color: #94a3b8;
        line-height: 1.5;
        min-height: 1.5em;
    }

    /* Submit */
    .setup-submit-row {
        display: flex;
        justify-content: center;
        margin-top: 2.25rem;
    }

    .setup-submit-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #4f5edd;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        padding: 0.85rem 2.75rem;
        border-radius: var(--landing-radius, 3px);
        border: 1px solid #3d4bc9;
        cursor: pointer;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, box-shadow 0.2s ease;
        letter-spacing: 0.01em;
    }

    .setup-submit-btn:hover:not(:disabled) { 
        background: #3d4bc9; 
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    }
    .setup-submit-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

    /* Spinner */
    .s-spinner {
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255,255,255,0.25);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
        display: inline-block;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 640px) {
        .setup-page { padding-inline: 1rem; }
        .setup-diff-row { grid-template-columns: repeat(2, 1fr); }
        .setup-diff-tab { border-bottom: 1px solid #1e2535; }
        .setup-diff-tab:nth-child(2n) { border-right: none; }
        .setup-select { max-width: 100%; width: 100%; }
        .setup-step-hint { margin-left: 0; width: 100%; margin-top: 0.25rem; }
        .setup-submit-btn { width: 100%; justify-content: center; }
    }
</style>

<div class="setup-page" x-data="setupForm()">

    {{-- Back nav --}}
    <div class="setup-header">
        <a href="{{ route('home') }}" class="setup-back">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Home
        </a>
        <h1 class="setup-title">Configure Debate Session</h1>
        <p class="setup-subtitle">Set your motion, opponent, language, side, and format before starting.</p>
    </div>

    <form action="{{ route('debates.create') }}" method="POST" id="setup-form">
        @csrf
        <input type="hidden" name="motion_text"  x-bind:value="motionText">
        <input type="hidden" name="user_side"    x-bind:value="selectedSide">
        <input type="hidden" name="persona_id"   x-bind:value="selectedPersona">
        <input type="hidden" name="difficulty"   x-bind:value="selectedDifficulty">
        <input type="hidden" name="mode"         x-bind:value="selectedMode">
        <input type="hidden" name="language"     x-bind:value="selectedLanguage">

        {{-- 01 MOTION --}}
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">01</span>
                <span class="setup-step-label">Debate Motion</span>
            </div>

            <textarea
                id="motion-input"
                class="setup-textarea"
                rows="3"
                placeholder="This House believes that… / এই সভা মনে করে যে…"
                x-model="motionText"
                x-on:input="motionSource = 'manual'"
            ></textarea>

            <div class="setup-generate-row">
                <span class="setup-or">or generate:</span>
                <select id="motion-category" class="setup-select" x-model="category" aria-label="Topic category for generated motion">
                    <option value="">Any topic</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
                <button type="button" id="btn-generate-motion"
                    class="setup-gen-btn"
                    x-on:click="generateMotion"
                    x-bind:disabled="generating"
                >
                    <template x-if="!generating">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                    </template>
                    <span x-show="!generating">Generate Motion</span>
                    <span x-show="generating" x-cloak style="display:inline-flex;align-items:center;gap:5px;">
                        <span class="s-spinner"></span> Generating…
                    </span>
                </button>
            </div>

            <p x-show="motionError" x-cloak role="alert" class="setup-gen-error" x-text="motionError"></p>

            <div x-show="motionSource === 'generated'" x-cloak>
                <span class="setup-generated-tag">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Generated by Gemini
                </span>
            </div>
        </div>

        {{-- 02 LANGUAGE --}}
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">02</span>
                <span class="setup-step-label">Debate Language</span>
                <span class="setup-step-hint">Affects audio, transcription &amp; AI responses</span>
            </div>

            <div class="setup-option-grid">
                <label class="setup-option-card" x-bind:class="selectedLanguage === 'en' ? 'selected' : ''">
                    <input type="radio" name="_language" value="en" x-on:change="selectedLanguage = 'en'">
                    <div class="setup-option-title">English</div>
                    <div class="setup-option-desc">Speak and debate in English</div>
                </label>
                <label class="setup-option-card" x-bind:class="selectedLanguage === 'bn' ? 'selected' : ''">
                    <input type="radio" name="_language" value="bn" x-on:change="selectedLanguage = 'bn'">
                    <div class="setup-option-title" style="font-family:'Noto Sans Bengali',sans-serif;">বাংলা</div>
                    <div class="setup-option-desc">Speak and debate in Bangla</div>
                </label>
            </div>
        </div>

        {{-- 03 SIDE --}}
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">03</span>
                <span class="setup-step-label">Your Side</span>
            </div>

            <div class="setup-option-grid">
                <label class="setup-option-card" x-bind:class="selectedSide === 'government' ? 'selected' : ''">
                    <input type="radio" name="_side" value="government" x-on:change="selectedSide = 'government'">
                    <div class="setup-option-title">Government</div>
                    <div class="setup-option-desc">Argue in favour of the motion</div>
                </label>
                <label class="setup-option-card" x-bind:class="selectedSide === 'opposition' ? 'selected' : ''">
                    <input type="radio" name="_side" value="opposition" x-on:change="selectedSide = 'opposition'">
                    <div class="setup-option-title">Opposition</div>
                    <div class="setup-option-desc">Argue against the motion</div>
                </label>
                <label class="setup-option-card" x-bind:class="selectedSide === 'auto' ? 'selected' : ''">
                    <input type="radio" name="_side" value="auto" x-on:change="selectedSide = 'auto'">
                    <div class="setup-option-title">Random</div>
                    <div class="setup-option-desc">Assigned at session start</div>
                </label>
            </div>
        </div>

        {{-- 04 PERSONA --}}
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">04</span>
                <span class="setup-step-label">AI Opponent Persona</span>
            </div>

            <div class="setup-persona-grid">
                @foreach($personas as $persona)
                <label class="setup-persona-card" x-bind:class="selectedPersona === '{{ $persona->id }}' ? 'selected' : ''">
                    <input type="radio" name="_persona" value="{{ $persona->id }}"
                           x-on:change="selectedPersona = '{{ $persona->id }}'">
                    <div class="setup-persona-name">
                        {{ $persona->name }}
                    </div>
                    <div class="setup-persona-desc">{{ $persona->description }}</div>
                </label>
                @endforeach
            </div>
        </div>

        {{-- 05 DIFFICULTY --}}
        @php
            $difficulties = [
                ['value' => 'beginner',       'label' => 'Beginner',       'desc' => 'Argues simply, lets minor weaknesses in your case go unchallenged.'],
                ['value' => 'intermediate',   'label' => 'Intermediate',   'desc' => 'Engages with your main points and uses concrete counter-examples.'],
                ['value' => 'advanced',       'label' => 'Advanced',       'desc' => 'Targets the weakest premise in your case with sophisticated rebuttals.'],
                ['value' => 'world_champion', 'label' => 'World Champion', 'desc' => 'Identifies the single weakest link in your argument and dismantles it.'],
            ];
        @endphp
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">05</span>
                <span class="setup-step-label">Difficulty Level</span>
            </div>

            <div class="setup-diff-row">
                @foreach($difficulties as $diff)
                <button type="button" id="diff-{{ $diff['value'] }}"
                    class="setup-diff-tab"
                    x-bind:class="selectedDifficulty === '{{ $diff['value'] }}' ? 'active' : ''"
                    :aria-pressed="selectedDifficulty === '{{ $diff['value'] }}'"
                    x-on:click="selectedDifficulty = '{{ $diff['value'] }}'">
                    {{ $diff['label'] }}
                </button>
                @endforeach
            </div>

            <div class="setup-diff-hint">
                @foreach($difficulties as $d)
                <template x-if="selectedDifficulty === '{{ $d['value'] }}'">
                    <span>{{ $d['desc'] }}</span>
                </template>
                @endforeach
            </div>
        </div>

        {{-- 06 FORMAT --}}
        <div class="setup-block">
            <div class="setup-block-head">
                <span class="setup-step-num">06</span>
                <span class="setup-step-label">Debate Format</span>
            </div>

            <div class="setup-option-grid">
                <label class="setup-option-card" x-bind:class="selectedMode === 'tournament' ? 'selected' : ''" style="flex:1; min-width:200px;">
                    <input type="radio" name="_mode" value="tournament" x-on:change="selectedMode = 'tournament'">
                    <div class="setup-option-title">
                        Tournament
                    </div>
                    <div class="setup-option-desc">Structured three-phase speeches — Opening, Rebuttal, Closing. Recommended for adjudication.</div>
                </label>
                <label class="setup-option-card" x-bind:class="selectedMode === 'sparring' ? 'selected' : ''" style="flex:1; min-width:200px;">
                    <input type="radio" name="_mode" value="sparring" x-on:change="selectedMode = 'sparring'">
                    <div class="setup-option-title">Sparring</div>
                    <div class="setup-option-desc">Adaptive conversational back-and-forth. Good for practising quick argument construction.</div>
                </label>
            </div>

            <div class="setup-standard-note">
                <template x-if="selectedMode === 'tournament'">
                    <div>
                        <strong>Standards followed:</strong> WSDC / Asian Parliamentary 1v1 structure.
                        Government opens every substantive phase (Opening, Rebuttal); Closing reverses — the
                        Opposition speaks first so the Government delivers the final word, as in official
                        reply speeches. If you are Government, the AI speaks first in Closing; if Opposition,
                        you speak first in Closing.
                    </div>
                </template>
                <template x-if="selectedMode === 'sparring'">
                    <div>
                        <strong>Standards followed:</strong> none — informal practice format. No fixed phases;
                        trade quick speeches in any order and end the round anytime to receive adjudication feedback.
                    </div>
                </template>
            </div>
        </div>

        {{-- Submit --}}
        <div class="setup-submit-row">
            <button type="submit" id="btn-start-debate" class="setup-submit-btn"
                x-bind:disabled="!motionText.trim() || !selectedSide || !selectedPersona">
                Begin Debate Session
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </form>
</div>

<script>
function setupForm() {
    return {
        motionText:         '',
        motionSource:       'manual',
        category:           '',
        selectedLanguage:   'en',
        selectedSide:       'government',
        selectedPersona:    '{{ $personas->first()?->id ?? '' }}',
        selectedDifficulty: 'beginner',
        selectedMode:       'tournament',
        generating:         false,
        motionError:        '',

        init() {
            document.addEventListener('keydown', (e) => {
                const tag = (e.target.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
                if (e.key === '/') {
                    e.preventDefault();
                    document.getElementById('motion-input')?.focus();
                }
            });
        },

        async generateMotion() {
            this.generating = true;
            this.motionError = '';
            try {
                const res = await fetch('{{ route('motions.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ language: this.selectedLanguage, category: this.category || null }),
                });
                const data = await res.json();
                if (data.motion) {
                    this.motionText   = data.motion;
                    this.motionSource = 'generated';
                } else {
                    this.motionError = data.error || 'Could not generate a motion right now. Please try again.';
                }
            } catch (err) {
                console.error('Motion generation failed:', err);
                this.motionError = 'Could not generate a motion right now. Please try again.';
            } finally {
                this.generating = false;
            }
        }
    }
}
</script>

@endsection

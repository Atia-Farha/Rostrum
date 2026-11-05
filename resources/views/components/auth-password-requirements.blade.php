{{--
    The live password requirement checklist.

    Three things are going on here, and the markup keeps them separate so
    each one does exactly one job:

    1. The VISIBLE list — always on screen, even before the field has been
       touched. A rule you cannot see is a rule you can only break. Each row
       states the rule plainly.

    2. The DETAIL line — appears under a row only once typing has started,
       and only for a rule that is not yet met, naming the one specific thing
       to add. Before the first keystroke the rules speak for themselves;
       afterwards, each unmet rule says what is missing instead of leaving
       the person to interpret "mixed case" on their own.

    3. The SUMMARY + TRACK — how much is left, in words and in a bar. The
       summary is a polite live region, so a screen reader hears the count
       change without the caller having to go looking for it. The track is
       decorative and hidden from assistive technology, because the summary
       already says the same thing in a better way.

    The server-rendered policy sentence lives in a visually-hidden node so
    the requirement is tied to the wording the backend enforces, and so the
    input's aria-describedby has a stable target even before Alpine boots.
--}}
@props([
    'id' => 'password-policy',

    // Whether to render the visually-hidden policy sentence.
    //
    // The sentence is the canonical policy in words, and it is what the
    // input points at. On the registration page the sentence is not
    // otherwise on screen, so it needs to be there. On the settings page
    // it already sits above the checklist as a visible hint — rendering
    // it a second time would put the same words on the page twice — so
    // that screen turns it off.
    'policy' => true,

    // Whether to use the compact form.
    //
    // The registration form is one column and the checklist is the
    // focus of the field it sits under, so it can afford a summary, a
    // track and four rows. On the settings page it is a supporting note
    // under a form that is about something else, so it is condensed to
    // a single row of four ticks.
    'compact' => false,
])

@php
    $class = 'form-rules'.($compact ? ' form-rules--compact' : '');
@endphp

{{--
    The id always lands on the root, whether or not the policy sentence
    is rendered. The input's aria-describedby points at this element, so
    it has to exist in both modes — a describedby that resolves to
    nothing is worse than no describedby at all, because a screen reader
    reports it as a broken reference.
--}}
<div class="{{ $class }}" id="{{ $id }}">
    {{-- Summary. Announced politely as it changes. --}}
    <div class="form-rules-summary" role="status" aria-live="polite">
        <span class="form-rules-summary-text" x-text="summary">Four requirements to meet</span>
        <span class="form-rules-badge" :class="{ 'is-done': score === total }" x-text="badgeLabel">0/4</span>
    </div>

    {{-- The decorative track. Hidden from AT: the summary carries the same
         information in words. --}}
    <div class="form-rules-track" aria-hidden="true">
        <template x-for="i in total" :key="i">
            <span class="form-rules-seg" :class="i <= score ? 'is-filled' : ''"></span>
        </template>
    </div>

    {{-- The rules. Each row is a plain list item so it is reachable and
         announced in reading order. --}}
    <ul class="form-rules-list">
        <li class="form-rule" :class="{ 'is-met': rules.length }">
            <span class="form-rule-icon" aria-hidden="true">
                <svg class="form-rule-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg class="form-rule-dot" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="form-rule-text">
                <span class="form-rule-label">10 or more characters</span>
                <span class="form-rule-detail" x-show="started && ! rules.length" x-cloak
                      x-text="`${Math.max(0, 10 - password.length)} more to go`"></span>
            </span>
        </li>

        <li class="form-rule" :class="{ 'is-met': rules.lower }">
            <span class="form-rule-icon" aria-hidden="true">
                <svg class="form-rule-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg class="form-rule-dot" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="form-rule-text">
                <span class="form-rule-label">One lowercase letter</span>
                <span class="form-rule-detail" x-show="started && ! rules.lower" x-cloak>needs a lowercase letter</span>
            </span>
        </li>

        <li class="form-rule" :class="{ 'is-met': rules.upper }">
            <span class="form-rule-icon" aria-hidden="true">
                <svg class="form-rule-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg class="form-rule-dot" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="form-rule-text">
                <span class="form-rule-label">One uppercase letter</span>
                <span class="form-rule-detail" x-show="started && ! rules.upper" x-cloak>needs an uppercase letter</span>
            </span>
        </li>

        <li class="form-rule" :class="{ 'is-met': rules.number }">
            <span class="form-rule-icon" aria-hidden="true">
                <svg class="form-rule-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <svg class="form-rule-dot" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <span class="form-rule-text">
                <span class="form-rule-label">One number</span>
                <span class="form-rule-detail" x-show="started && ! rules.number" x-cloak>needs a number</span>
            </span>
        </li>
    </ul>

    {{-- The policy in one sentence, for assistive technology. The visible
         rows above are the same rules in a scannable form; this gives AT the
         complete rule in a single read.

         The wording is the canonical policy sentence, identical to the one on
         the account-settings page. Keeping one sentence everywhere is what
         stops the stated policy from drifting away from the rule the server
         actually enforces.

         A screen that already shows the sentence passes :policy="false"
         and points its input at its own visible hint instead. --}}
    @if ($policy)
        <p class="form-sr-only" id="{{ $id }}-sentence">
            At least 10 characters, with an upper case letter, a lower case letter and a digit.
        </p>
    @endif
</div>

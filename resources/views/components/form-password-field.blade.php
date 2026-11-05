{{--
    A password input with a leading icon and a show/hide toggle.

    The toggle is a real <button type="button"> so it is keyboard reachable
    and announces its state to screen readers via aria-pressed.

    Props:
        id           — the input id (also used for the label's `for`)
        name         — the input name
        label        — visible label text
        model        — optional Alpine x-model binding, so another field
                       can read what is typed here
        reveal       — optional Alpine binding name for the toggle state;
                       when omitted the toggle keeps its own state
        autocomplete — autocomplete attribute value
        value        — prefill value (e.g. old() input)
        hint         — optional helper text rendered under the field
        required     — whether the field is required
        placeholder  — optional placeholder
        describedby  — optional extra id(s) the input points at
--}}
@props([
    'id',
    'name',
    'label' => 'Password',
    'model' => null,
    'reveal' => null,
    'autocomplete' => 'current-password',
    'value' => null,
    'hint' => null,
    'required' => true,
    'placeholder' => null,
    'describedby' => null,
])

@php
    $hasError = $errors->has($name);
    $hintId = $id . '-hint';
    $errorId = $id . '-error';

    $described = array_values(array_filter([
        $hasError ? $errorId : null,
        $hint ? $hintId : null,
        $describedby,
    ]));
@endphp

<div class="form-field">
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>

    {{-- When the toggle state is bound to a scope outside this field
         (the password form's own Alpine scope), that binding is used.
         Otherwise the field carries its own. Either way there is a
         scope for the toggle to read and write. --}}
    <div class="form-input-wrap" x-data="{ show: false }">
        <span class="form-input-icon" aria-hidden="true">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </span>

        <input
            class="form-input has-trailing"
            @if ($reveal)
                :type="{!! $reveal !!} ? 'text' : 'password'"
            @else
                :type="show ? 'text' : 'password'"
            @endif
            type="password"
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($value !== null) value="{{ $value }}" @endif
            @if ($model) x-model="{{ $model }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($described) aria-describedby="{{ implode(' ', $described) }}" @endif
            @if ($hasError) aria-invalid="true" @endif
        >

        <button type="button"
                class="form-reveal"
                @if ($reveal) @click="{{ $reveal }} = ! {{ $reveal }}" @else @click="show = ! show" @endif
                :aria-pressed="{!! $reveal ?? 'show' !!}"
                :aria-label="({!! $reveal ?? 'show' !!} ? 'Hide password' : 'Show password')">
            {{-- Eye (hidden when the password is visible) --}}
            <svg x-show="! {!! $reveal ?? 'show' !!}" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            {{-- Eye-off (shown when the password is visible) --}}
            <svg x-show="{!! $reveal ?? 'show' !!}" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    @if ($hint && ! $hasError)
        <span class="form-hint" id="{{ $hintId }}">{{ $hint }}</span>
    @endif

    @error($name)
        <span class="form-error" id="{{ $errorId }}" role="alert">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>{{ $message }}</span>
        </span>
    @enderror

    {{-- Anything that belongs under the control but inside the field: the
         requirements checklist, a live status line. --}}
    {{ $slot }}
</div>

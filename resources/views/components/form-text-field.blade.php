{{--
    A text input with a leading icon.

    The control is the shared form family, so a field on the sign-in
    form and a field on the account settings page are the same input:
    same recessed surface, same border, same icon slot, same focus ring.

    Props:
        id          — the input id (also the label's `for`)
        name        — the input name
        label       — visible label text
        icon        — 'user' or 'mail'
        type        — the input type (text, email, …)
        autocomplete— the autocomplete attribute value
        value       — prefill value (e.g. old() input)
        model       — optional Alpine x-model binding, for a field that
                      another part of the form needs to read
        hint        — optional helper text under the field
        placeholder — optional placeholder
        required    — whether the field is required
        describedby — optional extra id(s) the input points at
--}}
@props([
    'id',
    'name',
    'label',
    'icon' => 'user',
    'type' => 'text',
    'autocomplete' => null,
    'value' => null,
    'model' => null,
    'hint' => null,
    'placeholder' => null,
    'required' => true,
    'describedby' => null,
    'labelNote' => null,
    'autofocus' => false,
])

@php
    $hasError = $errors->has($name);
    $hintId = $id . '-hint';
    $errorId = $id . '-error';

    // The input points at everything that explains it: its own error
    // first, then its hint, then whatever the caller added.
    $described = array_values(array_filter([
        $hasError ? $errorId : null,
        $hint ? $hintId : null,
        $describedby,
    ]));
@endphp

<div class="form-field">
    <label class="form-label" for="{{ $id }}">
        <span>{{ $label }}</span>
        @if ($labelNote)
            <span class="form-label-optional">{{ $labelNote }}</span>
        @endif
    </label>

    <div class="form-input-wrap">
        <span class="form-input-icon" aria-hidden="true">
            @if ($icon === 'mail')
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            @else
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
            @endif
        </span>

        <input
            class="form-input"
            type="{{ $type }}"
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($value !== null) value="{{ $value }}" @endif
            @if ($model) x-model="{{ $model }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($described) aria-describedby="{{ implode(' ', $described) }}" @endif
            @if ($hasError) aria-invalid="true" @endif
        >
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
</div>

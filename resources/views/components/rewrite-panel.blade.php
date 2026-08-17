@props(['rewrite', 'isBn' => false, 'fontStyle' => '', 'visible' => false])

<div class="rewrite-panel {{ $visible ? 'visible' : '' }}" style="width: 100%; margin-top: 0.5rem;">
    <div style="padding:1rem; background:rgba(99,102,241,0.04); font-size:0.8rem; {{ $fontStyle }}">
        <p style="font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--color-accent); margin-bottom:0.5rem;">
            Enhanced Version
        </p>
        <p style="line-height:1.6; color:#f0f4ff;">{{ $rewrite->rewritten_text }}</p>
    </div>
    @if(!empty($rewrite->explanation_bullets))
    <div style="padding:0.7rem 1rem; border-top:1px solid var(--color-border); background:rgba(99,102,241,0.03);">
        <p style="font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--color-text-dim); margin-bottom:0.4rem; {{ $fontStyle }}">
            What changed
        </p>
        <ul style="font-size:0.75rem; color:var(--color-text-muted); list-style:none; {{ $fontStyle }}">
            @foreach($rewrite->explanation_bullets as $bullet)
            <li style="margin-bottom:0.2rem;">• {{ $bullet }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>

@extends('layouts.app')

@section('title', 'Page not found')

@section('content')
<div style="max-width:600px; margin:0 auto; padding:6rem 1.25rem; text-align:center;">
    <p style="font-size: clamp(3rem, 8vw, 4.5rem); font-weight:800; line-height:1; color:#818cf8; margin-bottom:1rem; font-variant-numeric:tabular-nums;">404</p>
    <h1 style="font-size: clamp(1.2rem, 3vw, 1.6rem); font-weight:700; color:#f0f4ff; margin-bottom:0.75rem;">This page is not in the House.</h1>
    <p style="font-size:0.95rem; color:#5c7090; margin-bottom:2rem; line-height:1.6;">
        The page you are looking for was moved, renamed, or never existed.
    </p>
    <a href="{{ route('home') }}" class="landing-btn-primary">Back to Rostrum</a>
</div>
@endsection

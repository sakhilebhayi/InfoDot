@extends('errors._ecosystem-layout', ['code' => 403])

@section('title', "You don't have access to this")
@section('message', "Either this belongs to a different team, needs a role you don't currently have, or requires signing in first. If you think this is wrong, check with whoever owns this workspace.")

@section('actions')
    <a href="/" class="ix-btn ix-btn--primary">Go home</a>
    @auth
        <a href="{{ route('dashboard') }}" class="ix-btn ix-btn--ghost">Open InfoDot</a>
    @else
        @if (Route::has('login'))
            <a href="{{ route('login') }}" class="ix-btn ix-btn--ghost">Sign in</a>
        @endif
    @endauth
@endsection

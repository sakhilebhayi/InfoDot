@extends('errors._ecosystem-layout', ['code' => 404])

@section('title', "We couldn't find that page")
@section('message', "The page you're looking for may have moved, been renamed, or never existed. Double-check the link, or head somewhere we know works.")

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

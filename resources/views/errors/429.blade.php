@extends('errors._ecosystem-layout', ['code' => 429])

@section('title', 'Slow down a little')
@php
    // How long the limit says to wait, when it says (the sign-up and reset forms do); rounded up to whole minutes.
    $wait = isset($exception) && method_exists($exception, 'getHeaders') ? (int) ($exception->getHeaders()['Retry-After'] ?? 0) : 0;
    $minutes = (int) ceil($wait / 60);
@endphp
@section('message', 'Too many requests came from your connection in a short time, so this one was not carried out. The limit protects everyone, and several people on one network can reach it together. '.($minutes > 1 ? 'Try again in about '.$minutes.' minutes.' : 'Wait a minute and try again.'))

@section('actions')
    <a href="{{ url()->previous() === url()->current() ? '/' : url()->previous() }}" class="ix-btn ix-btn--primary">Go back and try again</a>
    <a href="/" class="ix-btn ix-btn--ghost">Go home</a>
@endsection

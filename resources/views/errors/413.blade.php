@extends('errors._ecosystem-layout', ['code' => 413])

@section('icon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gold-deep)" stroke-width="1.75">
        <path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M14 3v5h5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M12 17v-5M9.75 14.25L12 12l2.25 2.25" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
@endsection

@section('title', 'That file is too large')
@php
    $limit = \App\Support\UploadLimit::megabytes();
@endphp
@section('message', ($limit === null ? '' : 'The limit for one upload is '.$limit.' MB. ').'Nothing was saved. Go back and choose a smaller file, or reduce this one and send it again.')

@section('actions')
    <a href="{{ url()->previous() === url()->current() ? '/' : url()->previous() }}" class="press" style="padding: 12px 24px; background: var(--gold); color: var(--charcoal); font-family: var(--font-display); font-weight: 600; font-size: 15px; border-radius: 999px; text-decoration: none;">Go back and choose a smaller file</a>
    <a href="/" class="press link-underline" style="padding: 12px 24px; color: var(--ink); font-size: 15px; text-decoration: none;">Go home</a>
@endsection

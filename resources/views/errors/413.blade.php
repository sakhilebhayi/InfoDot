@extends('errors._ecosystem-layout', ['code' => 413])

@section('title', 'That file is too large')
@php
    $limit = \App\Support\UploadLimit::megabytes();
@endphp
@section('message', ($limit === null ? '' : 'The limit for one upload is '.$limit.' MB. ').'Nothing was saved. Go back and choose a smaller file, or reduce this one and send it again.')

@section('actions')
    <a href="{{ url()->previous() === url()->current() ? '/' : url()->previous() }}" class="ix-btn ix-btn--primary">Go back and choose a smaller file</a>
    <a href="/" class="ix-btn ix-btn--ghost">Go home</a>
@endsection

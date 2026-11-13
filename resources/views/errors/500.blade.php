@extends('errors._ecosystem-layout', ['code' => 500])

@section('title', 'Something went wrong on our end')
@php
    // The id AssignRequestId gave this request: it is on the log line for this error and in the X-Request-Id header.
    $reference = \Illuminate\Support\Facades\Log::sharedContext()['request_id'] ?? null;
@endphp

@if (is_string($reference) && $reference !== '')
    @section('message', "That's on us, not you — an unexpected error interrupted this page. Try again in a moment; if it keeps happening, contact support and quote this reference so we can find exactly what failed.")
    @section('reference', $reference)
@else
    @section('message', "That's on us, not you — an unexpected error interrupted this page. Try again in a moment; if it keeps happening, let us know and we'll take a look.")
@endif

@section('actions')
    <a href="/" class="ix-btn ix-btn--primary">Go home</a>
    @if (Route::has('contact'))
        <a href="{{ route('contact') }}" class="ix-btn ix-btn--ghost">Contact support</a>
    @endif
@endsection

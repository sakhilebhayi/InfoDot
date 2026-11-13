{{-- R-50: why a platform did not open, and the one next step. Drawn in the error-page shell so it reads as a stop,
     not an outage; the list of other platforms is left off, because this page is about one of them. --}}
@extends('errors._ecosystem-layout', ['code' => 403, 'quiet' => true])

@section('title', $title)
@section('message', $message)

@section('actions')
    @if (session('status'))
        <p class="ix-hint" role="status" style="flex-basis: 100%; margin: 0;">{{ session('status') }}</p>
    @endif

    @if ($canActivate)
        <form method="POST" action="{{ route('platform.activate', ['platform' => $key]) }}" data-platform-activate>
            @csrf
            <input type="hidden" name="company" value="{{ $companyUuid }}">
            @if ($redirect)
                <input type="hidden" name="redirect" value="{{ $redirect }}">
            @endif
            <button type="submit" class="ix-btn ix-btn--primary">Switch on {{ $platform }}</button>
        </form>
    @elseif ($financeUrl)
        <a href="{{ $financeUrl }}" class="ix-btn ix-btn--primary">Open Dot.Finance</a>
    @elseif ($createCompany)
        <a href="{{ route('teams.create') }}" class="ix-btn ix-btn--primary">Create a company</a>
    @elseif ($support && Route::has('contact'))
        <a href="{{ route('contact') }}" class="ix-btn ix-btn--primary">Contact support</a>
    @endif

    <a href="{{ route('ecosystem.widget') }}" class="ix-btn {{ $canActivate || $financeUrl || $createCompany || $support ? 'ix-btn--ghost' : 'ix-btn--primary' }}">All platforms</a>
    <a href="{{ route('dashboard') }}" class="ix-btn ix-btn--ghost">Back to Home</a>
@endsection

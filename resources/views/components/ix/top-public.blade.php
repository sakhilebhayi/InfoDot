{{-- The shared Dot header for a visitor (R-52): the public pages, the directory and business pages opened signed out, the way in (sign in,
     register, reset) and the error pages. One 56px row on a desktop (52px on a phone) with the logo, the search centred by the header's grid,
     the three public places and Log in / Create account (or "Open InfoDot" for a signed-in visitor); a real menu drawer on a phone.
     Props: skip (print the header's own skip link: only where the layout has none, the error pages), landing (the page is its own search: no search in the bar), search (print the header search), way (the way in: no places,
     and never a link to the page already open), fallback (error pages: the name when the build is missing). --}}
@props(['landing' => false, 'search' => true, 'way' => false, 'fallback' => false, 'skip' => false])
@php
    $places = $way ? [] : [
        ['label' => 'Questions people ask', 'href' => route('faqs'), 'current' => request()->routeIs('faqs')],
        ['label' => 'Browse public answers', 'href' => route('solution_search_results'), 'current' => request()->routeIs('solution_search_results')],
        ['label' => 'Businesses', 'href' => route('businesses.index'), 'current' => request()->routeIs('businesses.*', 'business.show')],
    ];

    if (auth()->check()) {
        // A signed-in visitor on a public page: one way back into the app.
        $guest = ['register' => ['label' => 'Open InfoDot', 'href' => route('dashboard')]];
    } else {
        $guest = array_filter([
            'login' => \Illuminate\Support\Facades\Route::has('login') && ! request()->routeIs('login') ? ['label' => 'Sign in', 'href' => route('login')] : null,
            'register' => \Illuminate\Support\Facades\Route::has('register') && ! request()->routeIs('register') ? ['label' => 'Create account', 'href' => route('register')] : null,
        ]);
    }
@endphp
<x-dot.header
    brand-href="{{ $fallback ? '/' : url('/') }}" brand-label="InfoDot home"
    :nav="$places" nav-label="About InfoDot" :guest="$guest" :skip="$skip ? '#main' : null" drawer-title="Menu"
    class="ix-header ix-header--public {{ $landing ? 'is-landing' : '' }}">
    <x-slot:brand><x-ix.brand :fallback="$fallback" /></x-slot:brand>
    @if ($search && ! $landing)
        <x-slot:search><livewire:search /></x-slot:search>
    @endif
</x-dot.header>

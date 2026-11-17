{{-- The page's one h1 and the sentence under it. On a platform-started sign-in or registration the h1 is the platform's
     own headline (from the allowlisted branding, which the registry falls back for); elsewhere it is the page's title. --}}
@props(['title', 'kind' => null, 'brand' => null, 'lede' => null])
<h1 class="ix-auth-title">{{ $brand && $kind === 'login' ? $brand->headlineLogin : ($brand && $kind === 'register' ? $brand->headlineRegister : $title) }}</h1>
@if ($lede)
    <p class="ix-entry__lede">{{ $lede }}</p>
@endif

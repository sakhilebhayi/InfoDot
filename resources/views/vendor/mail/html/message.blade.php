<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{-- The logo comes from the build, like every other picture. A message is never held back for want of it: without a build the name stands in. --}}
@if ($logo = \App\Support\OptionalVite::asset('resources/img/brand/logo_white.png'))
<img src="{{ $logo }}" class="logo" alt="{{ config('app.name') }}">
@else
{{ config('app.name') }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}<br>
{{ __('The hub of the Dot Ecosystem.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

@auth
    <x-app-layout>
        @include('partials.search-results')
    </x-app-layout>
@else
    <x-public-layout title="Search">
        @include('partials.search-results')
    </x-public-layout>
@endauth

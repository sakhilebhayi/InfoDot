@auth
    <x-app-layout>
        @include('partials.search-results')
    </x-app-layout>
@else
    <x-public-layout title="Search" band="KNOW" crumb="Search">
        @include('partials.search-results')
    </x-public-layout>
@endauth

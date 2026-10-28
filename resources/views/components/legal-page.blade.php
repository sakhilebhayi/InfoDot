@props(['title', 'description', 'body'])
@php($doc = \App\Support\LegalDocument::prepare($body))
<x-public-layout :title="$title" band="LEGL" :crumb="$title" :description="$description">
    <div class="ix-legal">
        @if (count($doc['toc']) > 1)
            <nav class="ix-legal__toc" aria-label="On this page" x-data="{ open: window.matchMedia('(min-width: 900px)').matches }">
                <button type="button" class="ix-legal__toc-head" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'">
                    On this page
                    <span class="material-symbols-rounded" aria-hidden="true" x-text="open ? 'expand_less' : 'expand_more'">expand_less</span>
                </button>
                <ol x-show="open">
                    @foreach ($doc['toc'] as $item)
                        <li><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <article class="ix-prose ix-legal__body">
            <h1 class="ix-h1">{{ $title }}</h1>
            @if ($doc['updated'])<p class="ix-hint">Last updated {{ $doc['updated'] }}</p>@endif
            {!! $doc['html'] !!}
        </article>
    </div>
</x-public-layout>

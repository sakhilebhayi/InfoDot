{{-- The platform's side of a branded way-in page: its logo, what it does (the catalogue's own sentence), and a real screen of
     it, blurred so no demo data is legible. Identity is carried by colour, imagery and logo; the type is Roboto, the same as the form. --}}
@props(['brand'])
@php($logo = $brand->mode === 'dark' && $brand->logoDarkUrl ? $brand->logoDarkUrl : $brand->logoUrl)
<aside class="ix-auth__brand" data-mode="{{ $brand->mode }}" aria-label="{{ $brand->name }}" @if ($brand->backgroundUrl) style="background-image:url('{{ $brand->backgroundUrl }}')" @endif>
    @if ($logo)
        <img class="ix-auth__logo" src="{{ $logo }}" alt="{{ $brand->name }}" decoding="async">
    @else
        <p class="ix-auth__logo ix-auth__logo--name">{{ $brand->name }}</p>
    @endif

    <div class="ix-auth__pitch">
        <p class="ix-auth__name">{{ $brand->name }}</p>
        @if ($brand->description)<p class="ix-auth__desc">{{ $brand->description }}</p>@endif
        @if ($brand->promo)<p class="ix-auth__promo">{{ $brand->promo }}</p>@endif
    </div>

    @if ($brand->heroUrl)
        <img class="ix-auth__hero" src="{{ $brand->heroUrl }}" alt="{{ $brand->heroAlt ?? '' }}" @if (! $brand->heroAlt) aria-hidden="true" @endif width="1600" height="1000" loading="lazy" decoding="async">
    @elseif ($brand->heroSet !== [])
        <img class="ix-auth__hero" alt="" aria-hidden="true" width="1600" height="1000" loading="lazy" decoding="async"
             src="{{ $brand->heroSet[1]['url'] ?? $brand->heroSet[0]['url'] }}"
             srcset="{{ collect($brand->heroSet)->map(fn ($h) => $h['url'].' '.$h['width'].'w')->implode(', ') }}"
             sizes="(min-width: 900px) 50vw, 1px">
    @endif
</aside>

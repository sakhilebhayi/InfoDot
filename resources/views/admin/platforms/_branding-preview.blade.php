{{-- A plain summary of what the sign-in pages will show: words, colours and images, from the allowlisted view. --}}
<div class="ix-adm__brand" style="{{ $view->cssVariables() }}">
    <p><b>{{ $view->headlineLogin }}</b></p>
    <p>{{ $view->headlineRegister }}</p>
    @if ($view->description)<p class="ix-hint">{{ $view->description }}</p>@endif
    @if ($view->promo)<p class="ix-hint">{{ $view->promo }}</p>@endif
    <ul class="ix-adm__swatches">
        @foreach (['Primary' => $view->primary, 'Secondary' => $view->secondary, 'Accent' => $view->accent] as $label => $hex)
            <li><span class="ix-ref">{{ $label }}</span> <code>{{ $hex }}</code></li>
        @endforeach
        <li><span class="ix-ref">Mode</span> {{ $view->mode }}</li>
    </ul>
    @if ($view->logoUrl)<p><img src="{{ $view->logoUrl }}" alt="{{ $view->name }} logo" height="48" style="height:48px;width:auto"></p>@endif
    @if ($view->heroUrl)<p><img src="{{ $view->heroUrl }}" alt="{{ $view->heroAlt ?? '' }}" style="max-width:100%;height:auto;max-height:240px"></p>@endif
</div>

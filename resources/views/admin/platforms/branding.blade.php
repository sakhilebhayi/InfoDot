<x-app-layout title="Sign-in branding">
    @include('admin.platforms._tabs', ['current' => 'branding'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Sign-in branding</h1>
            <p class="ix-lede">How each registered platform looks on the central sign-in and registration pages when a person arrives from it. A platform with no branding, or with branding switched off, shows its name and colour from the registry.</p>
        </div>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel ix-panel--clip" aria-label="Platforms">
        @if ($platforms->isEmpty())
            <p class="ix-hint ix-panel__body">No platform is registered as a sign-in client yet, so nothing can be branded. Registering one is done on the host with <code>php artisan sso:platform register</code>; <code>php artisan sso:platform register-all</code> lists what would be registered.</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Platform</th><th>Sign-in client</th><th>Branding</th><th class="num">Version</th></tr></thead>
                    <tbody>
                        @foreach ($platforms as $platform)
                            <tr>
                                <td><a class="title" href="{{ route('admin.platforms.branding.edit', $platform) }}">{{ $platform->name }}</a> <span class="ix-ref">{{ $platform->slug }}</span></td>
                                <td data-label="Sign-in client"><span class="ix-chip {{ $platform->isActive() ? 'ix-chip--done' : 'ix-chip--open' }}">{{ ucfirst($platform->status) }}</span></td>
                                <td data-label="Branding">
                                    @if (! $platform->branding)<span class="ix-chip">From the registry</span>
                                    @elseif ($platform->branding->active)<span class="ix-chip ix-chip--done">Published</span>
                                    @else<span class="ix-chip ix-chip--open">Switched off</span>@endif
                                </td>
                                <td class="num" data-label="Version">{{ $platform->branding?->version ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>

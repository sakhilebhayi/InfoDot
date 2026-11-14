<x-app-layout title="Infrastructure">
    @include('admin.platforms._tabs', ['current' => 'infrastructure'])

    @php
        $status = [
            'operational' => ['Operational', 'ix-chip--done'], 'degraded' => ['Degraded', 'ix-chip--open'], 'maintenance' => ['Maintenance', 'ix-chip--open'],
            'unavailable' => ['Unavailable', 'ix-chip--open'], 'development' => ['In development', ''], 'not_launched' => ['Not launched', ''],
        ];
        $when = fn ($at) => $at?->timezone(config('app.timezone'))->format('j M Y, H:i');
        $count = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);
    @endphp

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Infrastructure</h1>
            <p class="ix-lede">What InfoDot has on record about each platform in the fleet: the status an administrator set, its entry in the registry, its sign-in registration, and how its deliveries are going. This is the state of the ecosystem, not of any company. A platform being operational here does not give a business access to it.</p>
        </div>
    </div>

    <section class="ix-panel ix-panel--clip" aria-label="Platforms on record">
        <div class="ix-tablewrap">
            <table class="ix-table ix-adm__table" data-infrastructure>
                <thead><tr><th scope="col">Platform</th><th scope="col">Catalogue status</th><th scope="col">Registry</th><th scope="col">Single sign-on</th><th scope="col">Last sign-in</th><th scope="col">Deliveries</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $entry = $row['entry'];
                            [$label, $tone] = $status[$entry->operational_status] ?? [$entry->operational_status, ''];
                            $ownSignIn = $row['own_sign_in'];
                            // A platform nothing is sent to: the core hub's bridge services and platforms with their own sign-in keep no secret here.
                            $needsSecret = $row['registry'] !== 'missing' && ! $ownSignIn;
                        @endphp
                        <tr data-platform="{{ $entry->key }}">
                            <td>
                                <a class="title" href="{{ route('admin.platforms.platform', $entry) }}">{{ $entry->name }}</a>
                                <span class="ix-ref">{{ $entry->key }}</span>
                            </td>
                            <td data-label="Catalogue status">
                                <span class="ix-chip {{ $tone }}">{{ $label }}</span>
                                <span class="ix-adm__fact">{{ ($row['changed_at'] ? 'Changed '.$when($row['changed_at']) : 'As first listed').($entry->discoverable ? '' : '. Hidden from businesses') }}</span>
                            </td>
                            <td data-label="Registry" data-registry="{{ $row['registry'] }}">
                                @switch($row['registry'])
                                    @case('live') Can be opened @break
                                    @case('off') <span class="ix-chip ix-chip--open">Switched off</span> @break
                                    @case('untrusted') <span class="ix-chip ix-chip--open">Address not trusted</span> @break
                                    @default <span class="ix-chip ix-chip--open">Not in the registry</span>
                                @endswitch
                                @if ($row['registry'] !== 'missing')
                                    <span class="ix-adm__fact">{{ ($ownSignIn ? 'Its own sign-in' : ($row['internal'] ? 'Internal service' : 'Signs in through InfoDot')).($needsSecret ? ', shared secret '.($row['secret'] ? 'set' : 'missing') : '') }}</span>
                                @endif
                            </td>
                            <td data-label="Single sign-on" data-sso="{{ $row['sso'] }}">
                                @if ($row['sso'] === 'missing')
                                    @if ($ownSignIn)<span class="ix-adm__soft">Not used: its own sign-in</span>@else<span class="ix-chip">Not registered</span>@endif
                                @elseif ($row['sso'] === 'active')
                                    Registered
                                @else
                                    <span class="ix-chip ix-chip--open">Registered, {{ $row['sso'] }}</span>
                                @endif
                                @if ($row['sso'] !== 'missing')<span class="ix-adm__fact">{{ $count($row['people'], 'person has', 'people have') }} signed in this way</span>@endif
                            </td>
                            <td class="muted" data-label="Last sign-in">{{ $row['last_sign_in_at'] ? $when($row['last_sign_in_at']) : 'None recorded' }}</td>
                            <td data-label="Deliveries">
                                @if ($row['waiting'] + $row['parked'] + $row['accepted'] + $row['unbuilt'] === 0)
                                    <span class="ix-adm__soft">Nothing sent yet</span>
                                @else
                                    @if ($row['parked'] > 0)<span class="ix-chip ix-chip--open">{{ $row['parked'] }} parked</span>@endif
                                    @if ($row['waiting'] > 0)<span class="ix-chip">{{ $row['waiting'] }} waiting</span>@endif
                                    {{ $row['accepted'] }} accepted
                                    <span class="ix-adm__fact">{{ implode('. ', array_filter([
                                        $row['last_accepted_at'] ? 'Last accepted '.$when($row['last_accepted_at']) : ($row['accepted'] === 0 ? 'None accepted yet' : null),
                                        $row['unbuilt'] > 0 ? $count($row['unbuilt'], 'event', 'events').' closed because the platform has no receiver yet' : null,
                                        $row['last_answer'] ? 'Last answer: '.$row['last_answer'] : null,
                                    ])) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($uncatalogued->isNotEmpty())
        <p class="ix-hint ix-vgap" data-uncatalogued>In the registry with no catalogue entry, so no business is shown {{ $uncatalogued->count() === 1 ? 'it' : 'them' }}: {{ $uncatalogued->implode(', ') }}.</p>
    @endif

    <section class="ix-prose ix-vgap" aria-labelledby="infra-read">
        <h2 id="infra-read">How to read this</h2>
        <ul>
            <li><strong>No uptime figure.</strong> InfoDot does not test whether a platform answers, so nothing here says one is up or down. The nearest facts are the last sign-in it completed and the last delivery it accepted.</li>
            <li><strong>Catalogue status</strong> is what an administrator set on the platform’s page. It changes only when someone changes it.</li>
            <li><strong>Last sign-in</strong> is the last time a person signed in to the platform through its single sign-on registration. Sign-ins by launch code are checked when they happen and are not kept, so they do not appear.</li>
            <li><strong>Deliveries</strong> are the entitlement changes sent to the platform. Waiting and parked events can be retried under <a href="{{ route('admin.platforms.outbox') }}">Deliveries</a>.</li>
        </ul>
    </section>
</x-app-layout>

@php
    // Every figure comes from the ledger and from configuration (LicenceOverview, SeatLedger::price); with no price set, nothing about price is shown.
    $ov ??= app(\App\Services\Licensing\LicenceOverview::class)->for($team);
    $seats = $ov['seats'];
    $isOwner = Gate::check('changeLicence', $team);
    $cur = $seats['price']['currency'] ?? null;
    $fm = fn (?int $minor, ?string $c = null) => $minor === null ? null : \App\Services\Licensing\SeatLedger::formatMoney($minor, $c ?? $cur);
    $money = $seats['price'] ? $fm($seats['price']['amount_minor_units']) : null;
    $moneyYear = $seats['price'] && $seats['price']['annual_amount_minor_units'] ? $fm($seats['price']['annual_amount_minor_units']) : null;
    $day = fn ($d) => $d?->timezone(config('app.timezone'))->format('j M Y');
    $labels = ['invited' => 'Invitation sent', 'joined' => 'Joined', 'left' => 'Left or removed', 'blocked' => 'Invitation blocked: no licence free', 'capacity_changed' => 'Licences changed', 'ownership_transferred' => 'Ownership transferred to', 'grandfathered' => 'Current people kept free for a transition period', 'grandfather_rolled_back' => 'Transition period undone',
        'batch_created' => 'Purchase started', 'batch_activated' => 'Purchase paid and active', 'batch_renewed' => 'Purchase renewed', 'batch_payment_failed' => 'Payment failed', 'batch_cancelled' => 'Purchase cancelled by the payment service', 'batch_cancel_requested' => 'Stop renewing requested', 'batch_reduction_scheduled' => 'Reduction scheduled for renewal', 'batch_refunded' => 'Purchase refunded', 'batch_ended' => 'Purchase ended', 'batch_payment_overdue' => 'Payment overdue',
        'licence_assigned' => 'Licence given to', 'licence_released' => 'Licence taken back from', 'licence_lapsed' => 'Licence ended for', 'licence_reactivated' => 'Licence restored for', 'licence_grace_started' => 'Time to fix licences started', 'licence_grace_cleared' => 'Licences back in order'];
    $stateLabel = ['free' => ['Free licence', 'info'], 'active' => ['Has a licence', 'done'], 'lapsed' => ['No licence: payment ended', 'open'], 'released' => ['No licence: taken back', 'open'], 'reserved' => ['Reserved', 'info']];
    $without = collect($ov['people'])->filter(fn ($p) => in_array($p['state'], ['lapsed', 'released'], true));
@endphp
<x-jet-action-section>
    <x-slot name="title">{{ __('Licences') }}</x-slot>
    <x-slot name="description">{{ $seats['included'] === 1
        ? __('A licence is one person in :team. The owner holds the one free licence; every other person needs a paid licence.', ['team' => $team->name])
        : __('A licence is one person in :team. The first :n are free; every other person needs a paid licence.', ['team' => $team->name, 'n' => $seats['included']]) }}</x-slot>
    <x-slot name="content">
        <div data-seats>
            @if (session('licence_ok'))<p class="ix-alert" role="status" data-licence-ok>{{ session('licence_ok') }}</p>@endif
            @if (session('licence_error'))<p class="ix-alert" role="alert" data-licence-error>{{ session('licence_error') }}</p>@endif

            <x-progress.bar :label="__('Licences in use')" :value="$seats['people']" :max="$seats['capacity']" :value-text="$seats['people'].' '.__('of').' '.$seats['capacity']"
                :tone="$seats['people'] >= $seats['capacity'] ? 'warn' : null" class="ix-seats__bar" />

            <div class="ix-tablewrap">
            <table class="ix-table">
                <tbody>
                    <tr><th scope="row">{{ __('Free licence') }}</th><td>{{ $seats['included'] }} <span class="ix-hint" style="display:inline">{{ $ov['owner']?->name }}</span></td></tr>
                    <tr><th scope="row">{{ __('Paid licences bought') }}</th><td>{{ $seats['purchased'] }}</td></tr>
                    <tr><th scope="row">{{ __('Licences in use') }}</th><td>{{ $seats['people'] }} {{ __('of') }} {{ $seats['capacity'] }}</td></tr>
                    <tr><th scope="row">{{ __('Held by unanswered invitations') }}</th><td>{{ $seats['reserved'] }}</td></tr>
                    <tr><th scope="row">{{ __('Licences available') }}</th><td>{{ $seats['available'] }}</td></tr>
                    @if ($ov['held'] > 0)
                        <tr><th scope="row">{{ __('Cost of your paid licences') }}</th>
                            <td>@if ($ov['cost']['month']){{ $fm($ov['cost']['month']['total_minor'], $ov['cost']['month']['currency']) }} {{ __('a month') }}@if ($ov['cost']['year']), {{ $fm($ov['cost']['year']['total_minor'], $ov['cost']['year']['currency']) }} {{ __('a year') }}@endif <span class="ix-hint" style="display:inline">{{ __('excluding VAT, at today\'s price') }}</span>@else{{ __('Not shown: no price has been set.') }}@endif</td></tr>
                    @endif
                </tbody>
            </table>
            </div>

            @if ($seats['grandfathered'])
                <p class="ix-hint">{{ __('This company keeps the people it has now free until :date. Nobody is removed. Anyone added beyond them needs a paid licence.', ['date' => $seats['grandfathered_until']->format('j F Y')]) }}</p>
            @endif
            @if ($seats['over_by'] > 0)
                <p class="ix-hint">{{ __('This company has :n more people than licences. Nobody is removed; you just cannot add anyone until there is room.', ['n' => $seats['over_by']]) }}</p>
            @endif

            @if ($money)
                <p class="ix-hint">{{ __('An extra licence costs :price.', ['price' => $money.' '.__('per person per month').($moneyYear ? ', '.$moneyYear.' '.__('per person per year') : '').', '.__('excluding VAT')]) }}</p>
            @endif

            <p style="margin-top: 16px;" class="ix-actions">
                <a class="ix-btn ix-btn--primary" href="{{ route('licences.buy', $team->uuid) }}" data-buy-link>{{ $isOwner ? __('Buy licences') : __('How licences work') }}</a>
            </p>
            @unless ($isOwner)<p class="ix-hint">{{ __('Only the owner can buy or change licences. Administrators can invite people while a licence is free.') }}</p>@endunless

            @if (count($ov['batches']) || $ov['pending']->isNotEmpty())
                <h3 class="ix-label" style="margin-top: 24px;">{{ __('Purchases') }}</h3>
                <ul class="ix-list" data-batches>
                    @foreach ($ov['pending'] as $b)
                        <li style="padding: 12px 14px;" data-batch-pending>
                            <strong>{{ $b->quantity }} {{ $b->quantity === 1 ? __('licence') : __('licences') }}</strong>, {{ $b->interval === 'year' ? __('yearly') : __('monthly') }}, {{ $fm($b->total_minor, $b->currency) }}
                            <span class="ix-chip ix-chip--open">{{ __('Waiting for payment') }}</span>
                            <span class="ix-hint">{{ __('Nobody has a licence from this until the payment is confirmed.') }}</span>
                            @if ($isOwner)<div class="ix-actions" style="margin-top:8px"><a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'close', 'batch' => $b->uuid]) }}">{{ __('Close this purchase') }}</a></div>@endif
                        </li>
                    @endforeach
                    @foreach ($ov['batches'] as $row)
                        @php($b = $row['batch'])
                        <li style="padding: 12px 14px;" data-batch="{{ $b->uuid }}">
                            <strong>{{ $b->quantity }} {{ $b->quantity === 1 ? __('licence') : __('licences') }}</strong>, {{ $b->interval === 'year' ? __('yearly') : __('monthly') }}, {{ $fm($b->total_minor, $b->currency) }}
                            @if ($b->status === 'active' && $b->cancelled_at === null)<span class="ix-chip ix-chip--done">{{ __('Active') }}</span>
                            @elseif ($b->status === 'active')<span class="ix-chip ix-chip--info">{{ __('Ending') }}</span>
                            @elseif ($b->status === 'grace')<span class="ix-chip ix-chip--open">{{ __('Payment failed') }}</span>
                            @elseif ($b->status === 'refunded')<span class="ix-chip ix-chip--open">{{ __('Refunded') }}</span>
                            @else<span class="ix-chip">{{ __('Ended') }}</span>@endif
                            <span class="ix-hint">
                                @if ($row['renews']){{ __('Next renewal :date.', ['date' => $day($row['renews'])]) }}
                                @elseif ($row['ends']){{ __('Stops renewing. The licences work until :date.', ['date' => $day($row['ends'])]) }}
                                @elseif ($b->status === 'grace' || $b->status === 'refunded'){{ __('The licences keep working until :date. Fix the payment, or the people beyond your licences lose access after that.', ['date' => $day($b->grace_until)]) }}
                                @elseif ($b->status === 'ended'){{ __('Ended :date.', ['date' => $day($b->period_end)]) }}@endif
                                @if ($b->quantity_next) {{ __('From the next renewal: :n licences.', ['n' => $b->quantity_next]) }}@endif
                            </span>
                            @if ($isOwner && $b->status === 'active' && $b->cancelled_at === null)
                                <div class="ix-actions" style="margin-top:8px">
                                    @if ($b->quantity > 1)
                                        <form method="get" action="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'reduce']) }}" class="ix-actions">
                                            <input type="hidden" name="batch" value="{{ $b->uuid }}">
                                            <label class="ix-hint" for="q-{{ $b->id }}" style="margin:0">{{ __('Renew as') }}</label>
                                            <input id="q-{{ $b->id }}" class="ix-input ix-input--tiny" type="number" name="quantity" min="1" max="{{ $b->quantity - 1 }}" value="{{ $b->quantity - 1 }}">
                                            <button class="ix-btn ix-btn--sm" type="submit">{{ __('Reduce at renewal') }}</button>
                                        </form>
                                    @endif
                                    <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'cancel', 'batch' => $b->uuid]) }}">{{ __('Stop renewing') }}</a>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3 class="ix-label" style="margin-top: 24px;">{{ __('Who holds a licence') }}</h3>
            <ul class="ix-list" data-licence-people>
                @foreach ($ov['people'] as $p)
                    @php($sl = $stateLabel[$p['state']] ?? ['Has a licence', 'done'])
                    <li style="padding: 12px 14px;" data-person="{{ $p['user']->getKey() }}">
                        <strong>{{ $p['user']->name }}</strong> <span class="ix-hint" style="display:inline">{{ $p['role'] === 'owner' ? __('Owner') : (\Laravel\Jetstream\Jetstream::findRole((string) $p['role'])?->name ?? ucfirst((string) $p['role'])) }}</span>
                        <span class="ix-chip ix-chip--{{ $sl[1] }}">{{ __($sl[0]) }}</span>
                        @if ($isOwner && ! $p['locked'])
                            <div class="ix-actions" style="margin-top:8px">
                                @if (in_array($p['state'], ['lapsed', 'released'], true))
                                    <a class="ix-btn ix-btn--sm" href="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'assign', 'user' => $p['user']->getKey()]) }}">{{ __('Give a licence') }}</a>
                                @else
                                    <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'release', 'user' => $p['user']->getKey()]) }}">{{ __('Take the licence back') }}</a>
                                    @if ($without->isNotEmpty())
                                        <form method="get" action="{{ route('licences.confirm', ['team' => $team->uuid, 'action' => 'reassign']) }}" class="ix-actions">
                                            <input type="hidden" name="user" value="{{ $p['user']->getKey() }}">
                                            <label class="ix-hint" for="to-{{ $p['user']->getKey() }}" style="margin:0">{{ __('Move it to') }}</label>
                                            <select id="to-{{ $p['user']->getKey() }}" class="ix-select ix-select--auto" name="to">
                                                @foreach ($without as $w)<option value="{{ $w['user']->getKey() }}">{{ $w['user']->name }}</option>@endforeach
                                            </select>
                                            <button class="ix-btn ix-btn--sm" type="submit">{{ __('Move') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="ix-hint">{{ __('Administrators always keep their licence. To remove someone from the company, use People and roles above; it names what they lose before you confirm.') }}</p>

            @if ($ov['invitations']->isNotEmpty())
                <p class="ix-hint" data-pending-invitations>{{ trans_choice(':n invitation is waiting to be answered and holds a licence.|:n invitations are waiting to be answered and each holds a licence.', $ov['invitations']->count(), ['n' => $ov['invitations']->count()]) }} <a href="#members">{{ __('See them') }}</a></p>
            @endif

            @if ($seats['available'] === 0)
                <p class="ix-hint" data-no-licence-free>
                    {{ $seats['can_buy'] && $seats['price']
                        ? __('Buy a licence to invite another person.')
                        : __('Every person beyond the free :n needs a paid licence, and paid licences cannot be bought just now. Nothing has been charged. You can cancel an unanswered invitation to free its licence.', ['n' => $seats['included']]) }}
                </p>
            @endif

            @if ($ov['history']->isNotEmpty())
                <h3 class="ix-label" style="margin-top: 20px;">{{ __('Purchase history') }}</h3>
                <ul class="ix-list" data-purchase-history>
                    @foreach ($ov['history'] as $event)
                        <li style="padding: 10px 14px;">{{ __($labels[$event->type] ?? $event->type) }} <span class="ix-ref">{{ $event->created_at->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            @endif
            <p class="ix-hint" data-invoices-note>{{ __('Invoices and receipts for every payment are issued by the payment service and sent to the owner by email. They are not shown here yet.') }}</p>

            @if ($ov['events']->isNotEmpty())
                <h3 class="ix-label" style="margin-top: 20px;">{{ __('Recent changes') }}</h3>
                <ul class="ix-list">
                    @foreach ($ov['events'] as $event)
                        <li style="padding: 10px 14px;">{{ __($labels[$event->type] ?? $event->type) }}@if($event->subject) — {{ $event->subject }}@endif <span class="ix-ref">{{ $event->created_at->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-slot>
</x-jet-action-section>

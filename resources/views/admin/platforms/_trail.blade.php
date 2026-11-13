{{-- A list of catalogue audit entries. Pass $events (with actor). --}}
@php
    $said = [
        'platform_updated' => 'Platform changed', 'plan_created' => 'Plan created', 'plan_updated' => 'Plan changed', 'price_approved' => 'Price approved',
        'price_approval_withdrawn' => 'Price approval withdrawn', 'bundle_created' => 'Bundle created', 'bundle_updated' => 'Bundle changed',
        'administrator_granted' => 'Administrator role granted', 'administrator_revoked' => 'Administrator role revoked', 'delivery_retried' => 'Delivery retried',
    ];
    $yesNo = ['discoverable', 'active', 'is_platform_admin', 'parked'];
    $show = fn ($value, $field = '') => match (true) {
        $value === null || $value === '' => 'empty',
        in_array($field, $yesNo, true) => $value ? 'yes' : 'no',
        is_array($value) => implode(', ', array_map('strval', $value)),
        default => \Illuminate\Support\Str::limit((string) $value, 80),
    };
@endphp
@if ($events->isEmpty())
    <p class="ix-hint">Nothing has been changed here yet.</p>
@else
    <ol class="ix-vhist">
        @foreach ($events as $event)
            <li>
                <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('j M Y, H:i') }}</time>
                <span>
                    {{ $said[$event->type] ?? ucfirst(str_replace('_', ' ', $event->type)) }}@if (! empty($withSubject)): <span class="ix-ref">{{ $event->subject_key }}</span>@endif
                    by {{ $event->actor?->name ?? 'the command line' }}
                    @foreach (($event->after ?? []) as $field => $value)
                        @continue(in_array($field, ['platform_key', 'slug'], true) || (($value === null || $value === '') && ! (is_array($event->before) && array_key_exists($field, $event->before))))
                        <span class="ix-vhist__note">{{ str_replace('_', ' ', $field) }}: @if (is_array($event->before) && array_key_exists($field, $event->before)){{ $show($event->before[$field], $field) }} to @endif{{ $show($value, $field) }}</span>
                    @endforeach
                    @if ($event->reason)<span class="ix-vhist__note">Reason: {{ $event->reason }}</span>@endif
                </span>
            </li>
        @endforeach
    </ol>
@endif

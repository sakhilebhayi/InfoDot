<x-app-layout title="Appointment types">
    @include('business.booking._nav', ['team' => $team, 'active' => 'types'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Appointment types</h1>
            <p class="ix-lede">The kinds of appointment {{ $team->name }} offers, how long each takes and who takes it.</p>
        </div>
        <a href="{{ route('business.booking.types.create', $team) }}" class="ix-btn ix-btn--primary">Add an appointment type</a>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    @unless ($hasHours)
        <p class="ix-alert" role="note">No business hours are set yet, so customers cannot book anything. <a href="{{ route('business.booking.hours', $team) }}">Set the hours</a>.</p>
    @endunless

    @if ($types->isEmpty())
        <section class="ix-panel" aria-label="Appointment types">
            <div class="ix-panel__body">
                <p>No appointment types yet. Add the first one.</p>
                <p class="ix-hint">A type is something a customer can book, for example a 30 minute consultation. Fees are shown as information only; nothing is charged here.</p>
            </div>
        </section>
    @else
        <section class="ix-panel ix-panel--clip" aria-label="Appointment types">
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>Name</th><th>Length</th><th>Fee</th><th>Format</th><th>Who takes it</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($types as $type)
                            <tr>
                                <td class="title">{{ $type->name }}</td>
                                <td data-label="Length">{{ $type->duration_minutes }} min</td>
                                <td data-label="Fee">{{ $type->fee_cents === null ? 'No fee' : $type->currency.' '.number_format($type->fee_cents / 100, 2, '.', ' ') }}</td>
                                <td data-label="Format">{{ \App\Models\AppointmentType::FORMATS[$type->meeting_format] ?? $type->meeting_format }}</td>
                                <td data-label="Who takes it" class="muted">{{ $type->providers->isEmpty() ? $team->name.' itself' : $type->providers->pluck('public_name')->implode(', ') }}</td>
                                <td data-label="Status"><span class="ix-chip @if($type->active) ix-chip--done @else ix-chip--open @endif">{{ $type->active ? 'On' : 'Off' }}</span></td>
                                <td>
                                    <div class="ix-actions">
                                        <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.booking.types.edit', [$team, $type]) }}">Edit</a>
                                        <form method="post" action="{{ route('business.booking.types.toggle', [$team, $type]) }}">@csrf
                                            <button type="submit" class="ix-btn ix-btn--sm ix-btn--ghost">{{ $type->active ? 'Switch off' : 'Switch on' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('business.booking.types.delete', [$team, $type]) }}" x-data x-on:submit="if (! confirm('Delete this appointment type? This cannot be undone.')) $event.preventDefault()">@csrf
                                            <button type="submit" class="ix-btn ix-btn--sm ix-btn--danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <p class="ix-hint">A type that already has appointments cannot be deleted; switch it off and it stops taking new ones.</p>
    @endif
</x-app-layout>

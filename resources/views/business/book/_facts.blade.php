@php
    $fee = $type->fee_cents === null ? 'No fee' : $type->currency.' '.number_format($type->fee_cents / 100, 2, '.', ' ').' (for information; nothing is charged through InfoDot)';
@endphp
<dl class="ix-biz__facts">
    <div><dt>Length</dt><dd>{{ $type->duration_minutes }} minutes</dd></div>
    <div><dt>Fee</dt><dd>{{ $fee }}</dd></div>
    <div><dt>Format</dt><dd>{{ \App\Models\AppointmentType::FORMATS[$type->meeting_format] ?? $type->meeting_format }}</dd></div>
    @if ($type->location_note)<div><dt>{{ $type->meeting_format === 'in_person' ? 'Where' : 'How to join' }}</dt><dd>{!! nl2br(e($type->location_note)) !!}</dd></div>@endif
    @if ($type->preparation)<div><dt>Preparation</dt><dd>{!! nl2br(e($type->preparation)) !!}</dd></div>@endif
</dl>

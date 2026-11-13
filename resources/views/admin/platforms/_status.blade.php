{{-- An entitlement's status as a chip. Pass $entitlement. The words say what the company can do now, not only the stored status. --}}
@php
    [$words, $tone] = match ($entitlement->status) {
        'active' => $entitlement->grants() ? ['Active', 'ix-chip--done'] : ['Ended', ''],
        'trial' => $entitlement->grants() ? ['Trial', 'ix-chip--info'] : ['Trial ended', ''],
        'pending' => ['Waiting for payment', 'ix-chip--open'],
        'payment_required' => $entitlement->grants() ? ['Payment failed, in grace', 'ix-chip--open'] : ['Payment required', 'ix-chip--open'],
        'suspended' => ['Suspended', 'ix-chip--open'],
        'cancelled' => $entitlement->grants() ? ['Cancelled, paid time left', ''] : ['Cancelled', ''],
        default => ['Expired', ''],
    };
@endphp
<span class="ix-chip {{ $tone }}">{{ $words }}</span>

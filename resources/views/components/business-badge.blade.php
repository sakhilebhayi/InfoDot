@props(['team'])
{{-- The verified-business plate (R-29 §12): shown only while InfoDot's verification is current. It
     opens a panel that names exactly what was checked and what verification does not mean. A
     pending, rejected, suspended, revoked or lapsed business shows nothing here. --}}
@php($standing = $team->standing)
@if ($standing?->isCurrent())
    <details {{ $attributes->merge(['class' => 'ix-vplate']) }}>
        <summary aria-label="Verified business — what this means"><span>Verified</span></summary>
        <div class="ix-vplate__panel" role="note">
            <p class="ix-vplate__title">{{ $team->name }} is verified by InfoDot</p>
            <dl class="ix-vplate__facts">
                <div><dt>Status</dt><dd>Verified, current</dd></div>
                <div><dt>Since</dt><dd>{{ $standing->verified_at?->format('j F Y') }}</dd></div>
                <div><dt>Review due</dt><dd>{{ $standing->expires_at?->format('j F Y') }}</dd></div>
                <div><dt>Kind</dt><dd>{{ \App\Models\BusinessVerification::CATEGORIES[$standing->category] ?? 'Business' }}</dd></div>
            </dl>
            <p class="ix-vplate__sub">What InfoDot confirmed</p>
            <ul class="ix-vplate__checks">
                @foreach ($standing->checks ?? [] as $check)
                    <li>{{ \App\Models\BusinessVerification::CHECKS[$check] ?? $check }}</li>
                @endforeach
            </ul>
            <p class="ix-vplate__fine">Verification confirms who the business is. It does not vouch for its finances, service quality, regulatory compliance or any single transaction. InfoDot does not check company registries or telephone numbers automatically.</p>
        </div>
    </details>
@endif

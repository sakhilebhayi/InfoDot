{{-- The admin centre's views (R-50). Shown only to platform administrators: the routes are behind the gate. --}}
<nav class="ix-mod__tabs" aria-label="Platform admin views">
    <a href="{{ route('admin.platforms') }}" @if ($current === 'catalogue') aria-current="page" @endif>Catalogue</a>
    <a href="{{ route('admin.platforms.bundles') }}" @if ($current === 'bundles') aria-current="page" @endif>Bundles</a>
    <a href="{{ route('admin.platforms.companies') }}" @if ($current === 'companies') aria-current="page" @endif>Companies</a>
    <a href="{{ route('admin.platforms.infrastructure') }}" @if ($current === 'infrastructure') aria-current="page" @endif>Infrastructure</a>
    <a href="{{ route('admin.platforms.outbox') }}" @if ($current === 'outbox') aria-current="page" @endif>Deliveries</a>
    <a href="{{ route('admin.platforms.payments') }}" @if ($current === 'payments') aria-current="page" @endif>Confirmed payments</a>
    <a href="{{ route('admin.platforms.audit') }}" @if ($current === 'audit') aria-current="page" @endif>Audit trail</a>
</nav>

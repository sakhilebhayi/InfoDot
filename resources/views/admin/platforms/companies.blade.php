<x-app-layout title="Companies">
    @include('admin.platforms._tabs', ['current' => 'companies'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Companies</h1>
            <p class="ix-lede">Find a company by its name or owner code to see the platforms it holds, read the history, and grant, suspend, restore or cancel access.</p>
        </div>
    </div>

    <form method="get" action="{{ route('admin.platforms.companies') }}" class="ix-dir__filters" role="search" aria-label="Find a company">
        <label class="ix-dir__filter" style="grid-column: 1 / -1"><span>Company name or owner code</span><input class="ix-input" type="search" name="q" maxlength="120" value="{{ $q }}" autocomplete="off"></label>
        <button type="submit" class="ix-btn">Find</button>
        @if ($q !== '')<a class="ix-btn ix-btn--ghost" href="{{ route('admin.platforms.companies') }}">Clear</a>@endif
    </form>
    @if ($errors->any())<div class="ix-error ix-vgap" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @if ($q !== '' && ! $searching)<p class="ix-hint ix-vgap">Type at least two characters to search.</p>@endif

    <section class="ix-panel ix-panel--clip ix-vgap" aria-label="{{ $searching ? 'Companies found' : 'Companies whose platforms changed most recently' }}">
        <div class="ix-panel__head">
            <h2>{{ $searching ? 'Matching “'.$q.'”' : 'Most recently changed' }}</h2>
            <span class="ix-hint">{{ $searching ? $companies->total() : $companies->count() }}</span>
        </div>
        @if ($companies->isEmpty())
            <p class="ix-hint ix-panel__body">{{ $searching ? 'No company has that in its name or owner code. Check the spelling, or try part of the name.' : 'No company has switched a platform on yet. Every company holds the free core without a record here; search for one to grant it more.' }}</p>
        @else
            <div class="ix-tablewrap">
                <table class="ix-table ix-adm__table">
                    <thead><tr><th>Company</th><th>Owner</th><th class="num">People</th><th>Platforms beyond the core</th></tr></thead>
                    <tbody>
                        @foreach ($companies as $company)
                            @php $own = $held[$company->id] ?? collect(); @endphp
                            <tr>
                                <td><a class="title" href="{{ route('admin.platforms.company', $company) }}">{{ $company->name }}</a> <span class="ix-ref">{{ $company->code }}</span> <x-demo-mark :of="$company" /></td>
                                <td class="muted" data-label="Owner">{{ $company->owner?->name ?? 'No owner' }}</td>
                                <td class="num" data-label="People">{{ $company->users_count + 1 }}</td>
                                <td data-label="Platforms beyond the core">
                                    @if ($own->isEmpty())
                                        <span class="muted">None</span>
                                    @else
                                        {{ $own->filter->grants()->count() }} in use @if ($own->where('status', 'suspended')->isNotEmpty()) and <span class="ix-chip ix-chip--open">{{ $own->where('status', 'suspended')->count() }} suspended</span>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($searching)
        @include('partials.pager', ['pager' => $companies, 'label' => 'Companies'])
    @endif
</x-app-layout>

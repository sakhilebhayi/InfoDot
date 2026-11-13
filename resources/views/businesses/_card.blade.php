{{-- One business in the directory: its own logo, name and words, the categories it chose, and —
     when it came from a search — what it matched on. Nothing here is inferred about the business. --}}
@php
    $p = $team->profile;
    $type = \App\Models\TeamProfile::BUSINESS_TYPES[$p?->business_type] ?? null;
@endphp
<li class="ix-bizcard">
    <span class="ix-biz__logo ix-bizcard__logo" @if(! $team->logo?->onDisk()) aria-hidden="true" @endif>
        @if ($team->logo?->onDisk())
            <img src="{{ route('attachments.show', $team->logo) }}" alt="" width="56" height="56" loading="lazy">
        @else
            {{ mb_substr(trim($team->name), 0, 1) }}
        @endif
    </span>
    <div class="ix-bizcard__body">
        <div class="ix-bizcard__name">
            <h3><a href="{{ route('business.show', $team) }}">{{ $team->name }}</a></h3>
            <x-business-badge :team="$team" />
        </div>
        <p class="ix-bizcard__meta">
            @if ($team->code)<span class="ix-ref">{{ $team->code }}</span>@endif
            @if ($p?->location)<span><span class="material-symbols-rounded" aria-hidden="true">location_on</span>{{ $p->location }}</span>@endif
            @if ($type)<span>{{ $type }}</span>@endif
        </p>
        @if ($p?->description)
            <p class="ix-bizcard__desc">{{ \Illuminate\Support\Str::limit($p->description, 180) }}</p>
        @endif
        @if ($team->categories->isNotEmpty() || $team->services->isNotEmpty())
            <ul class="ix-bizcard__tags" aria-label="Categories and services">
                @foreach ($team->categories as $c)<li class="is-cat">{{ $c->name }}</li>@endforeach
                @foreach ($team->services->take(4) as $s)<li>{{ $s->name }}</li>@endforeach
                @if ($team->services->count() > 4)<li class="is-more">{{ $team->services->count() - 4 }} more</li>@endif
            </ul>
        @endif
        @if ($why)
            <p class="ix-bizcard__why">@if (str_contains($why[0], ':')){{ implode('. ', $why) }}@else<span>Matched on</span> {{ implode(', ', $why) }}@endif</p>
        @endif
    </div>
</li>

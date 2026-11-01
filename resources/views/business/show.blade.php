<x-app-layout :title="$team->name">
    @php
        $profile ??= null;
        $links = $profile?->shows('links') ? array_filter($profile->links ?? []) : [];
        $facts = array_filter([
            'Type' => \App\Models\TeamProfile::BUSINESS_TYPES[$profile?->business_type] ?? null,
            'Industry' => $profile?->industry,
            'Category' => $profile?->category,
            'Location' => $profile?->location,
            'Service areas' => $profile?->regions,
            'Established' => $profile?->established_on?->format('Y'),
            'Size' => $profile?->size,
            // Members see it whole; everyone else sees enough to recognise it, not to reuse it (R-29 §26).
            'Registration no.' => $profile?->registration_number
                ? ($isMember ? $profile->registration_number : str_repeat('•', max(0, mb_strlen($profile->registration_number) - 3)).mb_substr($profile->registration_number, -3))
                : null,
            'Registered in' => $profile?->registration_country,
        ]);
        // Contact actions use only what the business itself chose to publish (R-33 §5B, §11).
        $phone = $profile?->contact_phone && $profile->shows('phone') ? $profile->contact_phone : null;
        $tel = $phone ? preg_replace('/[^0-9+]/', '', $phone) : null;
        $email = $profile?->contact_email && $profile->shows('email') ? $profile->contact_email : null;
        $website = $profile?->website && $profile->shows('website') ? $profile->website : null;
        $where = $profile?->location;
        $addressed = $team->locations->first(fn ($l) => $l->show_address && $l->address);
        $mapUrl = $addressed?->mapUrl($team->name)
            ?? ($where ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($team->name.', '.$where) : null);
        $hidden = $canEdit ? collect(\App\Models\TeamProfile::CONTACT_FIELDS)->filter(fn ($label, $key) => ! $profile?->shows($key))->values() : collect();
        $public = $profile?->visibility === \App\Models\TeamProfile::PUBLIC;
        $sectionNo = 0;
        $no = function () use (&$sectionNo) { return str_pad((string) ++$sectionNo, 2, '0', STR_PAD_LEFT); };
        $products = $team->services->where('kind', 'product');
        $services = $team->services->where('kind', '!=', 'product');
        $standing = $team->standing;
    @endphp

    @push('meta')
        @if ($profile?->description)<meta name="description" content="{{ \Illuminate\Support\Str::limit($profile->description, 155) }}">@endif
        @if ($public && ! $preview)
            <link rel="canonical" href="{{ route('business.show', $team) }}">
        @else
            {{-- Only businesses listed for everyone may be indexed. --}}
            <meta name="robots" content="noindex">
        @endif
    @endpush

    @if ($preview)
        <div class="ix-previewbar" role="status">
            <p><b>Preview: this is the page as someone outside {{ $team->name }} sees it.</b>
                @if ($profile?->visibility === \App\Models\TeamProfile::PRIVATE) Right now the business is set to “only this business”, so nobody outside it can open the page at all.
                @elseif ($profile?->visibility === \App\Models\TeamProfile::MEMBERS) Right now only people signed in to InfoDot can open it.
                @else Anyone can open it, signed in or not. @endif
            </p>
            <a class="ix-btn ix-btn--sm" href="{{ route('business.show', $team) }}">Leave the preview</a>
        </div>
    @endif

    <div class="ix-biz">
        <figure class="ix-biz__yard">
            @if ($cover)
                <img class="ix-biz__cover" src="{{ route('attachments.show', $cover) }}" alt="{{ $team->name }} — cover photograph" width="{{ $cover->meta['width'] ?? 1600 }}" height="{{ $cover->meta['height'] ?? 500 }}">
            @else
                <div class="ix-biz__field" aria-hidden="true"><span>{{ $team->code ?: 'IDNT' }}</span></div>
            @endif
            <figcaption class="ix-biz__plate">
                <span class="ix-biz__logo" @if(!$logo) aria-hidden="true" @endif>
                    @if ($logo)
                        <img src="{{ route('attachments.show', $logo) }}" alt="{{ $team->name }} logo" width="96" height="96">
                    @else
                        {{ mb_substr(trim($team->name), 0, 1) }}
                    @endif
                </span>
                <div class="ix-biz__who">
                    <div class="ix-biz__name">
                        <h1 class="ix-h1">{{ $team->name }}</h1>
                        <x-business-badge :team="$team" />
                    </div>
                    <p class="ix-biz__meta">
                        @if ($team->code)<span class="ix-ref">{{ $team->code }}</span>@endif
                        @if ($profile?->trading_name && $profile->trading_name !== $team->name)<span>trading as {{ $profile->trading_name }}</span>@endif
                        @if ($profile?->industry)<span>{{ $profile->industry }}</span>@endif
                        @if ($where)<span>{{ $where }}</span>@endif
                    </p>
                    @if ($team->categories->isNotEmpty())
                        <ul class="ix-bizcard__tags" aria-label="Categories">
                            @foreach ($team->categories as $c)<li class="is-cat"><a href="{{ route('businesses.index', ['category' => $c->slug]) }}">{{ $c->name }}</a></li>@endforeach
                        </ul>
                    @endif
                </div>
                @if ($canEdit)
                    <div class="ix-biz__editbtn">
                        <a href="{{ route('business.show', [$team, 'as' => 'visitor']) }}" class="ix-btn ix-btn--ghost">
                            <span class="material-symbols-rounded" aria-hidden="true">visibility</span>Preview as a visitor
                        </a>
                        <a href="{{ route('business.edit', $team) }}" class="ix-btn ix-btn--ghost">
                            <span class="material-symbols-rounded" aria-hidden="true">edit</span>Edit profile
                        </a>
                    </div>
                @endif
            </figcaption>
        </figure>

        <div class="ix-biz__cols">
            <div class="ix-biz__main">
                @if ($announcements->isNotEmpty())
                    <section class="ix-bizsec" aria-labelledby="news-h">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl" id="news-h">From {{ $team->name }}</h2></div>
                        <ul class="ix-news">
                            @foreach ($announcements as $a)
                                <li>
                                    <h3>{{ $a->title }}</h3>
                                    <p class="ix-news__when"><time datetime="{{ $a->published_at->toDateString() }}">{{ $a->published_at->format('j M Y') }}</time>@if ($a->ends_at), until {{ $a->ends_at->format('j M Y') }}@endif</p>
                                    <p class="ix-biz__prose">{{ $a->body }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($profile?->description || $profile?->industries_served)
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl">What this business does</h2></div>
                        @if ($profile?->description)<p class="ix-biz__prose">{{ $profile->description }}</p>@endif
                        @if ($profile?->industries_served)
                            <p class="ix-served"><span>Industries served</span> {{ $profile->industries_served }}</p>
                        @endif
                    </section>
                @endif

                @foreach ([['Services', $services], ['Products', $products]] as [$heading, $items])
                    @if ($items->isNotEmpty())
                        <section class="ix-bizsec">
                            <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl">{{ $heading }}</h2></div>
                            <ul class="ix-offers">
                                @foreach ($items as $item)
                                    @php
                                        $pic = $offeringPictures->get($item->id)?->last();
                                    @endphp
                                    <li @class(['has-pic' => $pic])>
                                        @if ($pic)
                                            <img src="{{ route('attachments.show', $pic) }}" alt="{{ $item->name }}" loading="lazy" width="{{ $pic->meta['width'] ?? 800 }}" height="{{ $pic->meta['height'] ?? 600 }}">
                                        @endif
                                        <div class="ix-offers__body">
                                            <h3>{{ $item->name }}</h3>
                                            @if ($item->description)<p>{{ $item->description }}</p>@endif
                                            @if ($item->price_note)<p class="ix-offers__price"><span>Price</span> {{ $item->price_note }}</p>@endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endforeach

                @if ($profile?->products_services)
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl">{{ $team->services->isEmpty() ? 'Products and services' : 'More about what they offer' }}</h2></div>
                        <p class="ix-biz__prose">{{ $profile->products_services }}</p>
                    </section>
                @endif

                @if ($team->projects->isNotEmpty())
                    <section class="ix-bizsec" aria-labelledby="work-h">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl" id="work-h">Portfolio</h2></div>
                        <ol class="ix-work">
                            @foreach ($team->projects as $project)
                                @php
                                    $pics = $projectPictures->get($project->id) ?? collect();
                                @endphp
                                <li>
                                    <div class="ix-work__head">
                                        <h3>{{ $project->title }}</h3>
                                        <p class="ix-bizcard__meta">
                                            @if ($project->year)<span>{{ $project->year }}</span>@endif
                                            @if ($project->location)<span><span class="material-symbols-rounded" aria-hidden="true">location_on</span>{{ $project->location }}</span>@endif
                                        </p>
                                    </div>
                                    @if ($project->summary)<p class="ix-biz__prose">{{ $project->summary }}</p>@endif
                                    @if ($pics->isNotEmpty())
                                        <ul class="ix-work__pics">
                                            @foreach ($pics as $shot)
                                                <li><figure>
                                                    <img src="{{ route('attachments.show', $shot) }}" alt="{{ $shot->caption ?: $project->title.' — photograph' }}" loading="lazy" width="{{ $shot->meta['width'] ?? 1600 }}" height="{{ $shot->meta['height'] ?? 1200 }}">
                                                    @if ($shot->caption)<figcaption>{{ $shot->caption }}</figcaption>@endif
                                                </figure></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($team->locations->isNotEmpty())
                    <section class="ix-bizsec" aria-labelledby="loc-h">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl" id="loc-h">Locations</h2></div>
                        <ul class="ix-locs">
                            @foreach ($team->locations as $loc)
                                <li>
                                    <b>{{ $loc->label }}</b>
                                    <span>{{ $loc->publicLine() }}</span>
                                    <a href="{{ $loc->mapUrl($team->name) }}" rel="noopener nofollow" target="_blank">{{ $loc->show_address && $loc->address ? 'Directions' : 'On the map' }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($gallery->isNotEmpty())
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl">In the yard</h2></div>
                        <ul class="ix-biz__gallery">
                            @foreach ($gallery as $shot)
                                <li>
                                    <figure>
                                        <img src="{{ route('attachments.show', $shot) }}" alt="{{ $shot->caption ?: $team->name.' — photograph' }}" loading="lazy" width="{{ $shot->meta['width'] ?? 1600 }}" height="{{ $shot->meta['height'] ?? 1200 }}">
                                        @if ($shot->caption)<figcaption>{{ $shot->caption }}</figcaption>@endif
                                    </figure>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($brochures->isNotEmpty())
                    <section class="ix-bizsec" aria-labelledby="doc-h">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ $no() }}</span><h2 class="ix-bizsec__lbl" id="doc-h">Brochures</h2></div>
                        <ul class="ix-docs">
                            @foreach ($brochures as $doc)
                                <li>
                                    <span class="material-symbols-rounded" aria-hidden="true">picture_as_pdf</span>
                                    <a href="{{ route('attachments.show', $doc) }}" target="_blank" rel="noopener">{{ $doc->caption ?: $doc->name }}</a>
                                    <span class="ix-hint">PDF, {{ $doc->sizeLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($questions->isNotEmpty() || $solutions->isNotEmpty())
                    <section class="ix-fieldblock">
                        <div class="ix-fieldblock__head"><span class="ix-fieldblock__num">{{ $no() }}</span><h2 class="ix-fieldblock__lbl">On InfoDot</h2></div>
                        <ul class="ix-biz__activity">
                            @foreach ($questions as $question)
                                <li>
                                    <span class="ix-chip ix-chip--open">Question</span>
                                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}">{{ $question->question }}</a>
                                    <time datetime="{{ $question->created_at->toDateString() }}">{{ $question->created_at->format('j M Y') }}</time>
                                </li>
                            @endforeach
                            @foreach ($solutions as $solution)
                                <li>
                                    <span class="ix-chip ix-chip--done">Solution</span>
                                    <a href="{{ route('solutions.view', ['id' => $solution->id]) }}">{{ $solution->solution_title }}</a>
                                    <time datetime="{{ $solution->created_at->toDateString() }}">{{ $solution->created_at->format('j M Y') }}</time>
                                </li>
                            @endforeach
                        </ul>
                        @unless ($isMember)
                            <p class="ix-fieldblock__foot">Only what this business has shared with everyone is listed here.</p>
                        @endunless
                    </section>
                @endif

                @if ($sectionNo === 0)
                    <div class="ix-empty">
                        <h2>{{ $team->name }} has not filled in its profile yet</h2>
                        <p>@if ($canEdit)Say what the business does, where it operates and what it looks like — that is what people here see first.@else This business has not said anything about itself yet.@endif</p>
                        @if ($canEdit)<a href="{{ route('business.edit', $team) }}" class="ix-btn ix-btn--primary">Fill in the profile</a>@endif
                    </div>
                @endif
            </div>

            <aside class="ix-biz__rail" aria-label="Business facts">
                @if ($facts)
                    <section class="ix-biz__card">
                        <h2>Registry</h2>
                        <dl class="ix-biz__facts">
                            @foreach ($facts as $label => $value)
                                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($tel || $email || $website || $links || $mapUrl || $hidden->isNotEmpty())
                    <section class="ix-biz__card">
                        <h2>Reach them</h2>
                        <div class="ix-reach">
                            @if ($tel)<a class="ix-btn ix-btn--primary" href="tel:{{ $tel }}"><span class="material-symbols-rounded" aria-hidden="true">call</span>Call</a>@endif
                            @if ($email)<a class="ix-btn @unless($tel) ix-btn--primary @endunless" href="mailto:{{ $email }}"><span class="material-symbols-rounded" aria-hidden="true">mail</span>Email</a>@endif
                            @if ($website)<a class="ix-btn" href="{{ $website }}" rel="noopener nofollow" target="_blank"><span class="material-symbols-rounded" aria-hidden="true">language</span>Website</a>@endif
                            @if ($mapUrl)<a class="ix-btn" href="{{ $mapUrl }}" rel="noopener nofollow" target="_blank"><span class="material-symbols-rounded" aria-hidden="true">{{ $addressed ? 'directions' : 'map' }}</span>{{ $addressed ? 'Directions' : 'On the map' }}</a>@endif
                        </div>
                        <dl class="ix-biz__facts">
                            @if ($website)<div><dt>Website</dt><dd><a href="{{ $website }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $website), 40) }}</a></dd></div>@endif
                            @if ($email)<div><dt>Email</dt><dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd></div>@endif
                            @if ($phone)<div><dt>Phone</dt><dd>{{ $phone }}</dd></div>@endif
                            @if ($addressed)<div><dt>Address</dt><dd>{{ $addressed->publicLine() }}</dd></div>@endif
                            @foreach ($links as $key => $url)
                                <div><dt>{{ ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube'][$key] ?? ucfirst($key) }}</dt><dd><a href="{{ $url }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $url), 40) }}</a></dd></div>
                            @endforeach
                        </dl>
                        @if ($hidden->isNotEmpty())
                            <p class="ix-trust__line">Kept off the page by your choice: {{ mb_strtolower($hidden->implode(', ')) }}. <a href="{{ route('business.edit', $team) }}#contact">Change</a></p>
                        @endif
                    </section>
                @endif

                <section class="ix-biz__card" aria-labelledby="trust-h">
                    <h2 id="trust-h">InfoDot verification</h2>
                    @if ($standing?->isCurrent())
                        <div class="ix-trust__state"><span class="ix-chip ix-chip--done">Verified</span><span class="ix-hint">since {{ $standing->verified_at?->format('j M Y') }}, review due {{ $standing->expires_at?->format('M Y') }}</span></div>
                        <ul class="ix-trust__checks">
                            @foreach ($standing->checks ?? [] as $check)<li>{{ \App\Models\BusinessVerification::CHECKS[$check] ?? $check }}</li>@endforeach
                        </ul>
                        <p class="ix-trust__line">Everything else on this page is the business’s own description of itself.</p>
                    @elseif (in_array($standing?->status, [\App\Models\BusinessVerification::UNDER_REVIEW, \App\Models\BusinessVerification::SUSPENDED], true))
                        <div class="ix-trust__state"><span class="ix-chip ix-chip--open">Under review</span></div>
                        <p class="ix-trust__line">InfoDot is reviewing this business’s verification. Until that is done it is not shown as verified.</p>
                    @else
                        <div class="ix-trust__state"><span class="ix-chip">Not verified</span></div>
                        <p class="ix-trust__line">InfoDot has not verified this business. What it says here is its own description of itself.</p>
                    @endif
                    @if ($canEdit)
                        <p class="ix-trust__line"><a href="{{ route('business.verification', $team) }}">{{ $standing?->isCurrent() ? 'Verification details' : 'Verify this business' }}</a></p>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>

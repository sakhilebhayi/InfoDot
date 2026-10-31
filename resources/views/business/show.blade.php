<x-app-layout>
    @php
        $profile ??= null;
        $links = array_filter($profile?->links ?? []);
        $facts = array_filter([
            'Industry' => $profile?->industry,
            'Category' => $profile?->category,
            'Location' => $profile?->location,
            'Operates in' => $profile?->regions,
            'Established' => $profile?->established_on?->format('Y'),
            'Size' => $profile?->size,
            'Registration no.' => $profile?->registration_number,
            'Registered in' => $profile?->registration_country,
        ]);
    @endphp

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
                    <h1 class="ix-h1">{{ $team->name }}</h1>
                    <p class="ix-biz__meta">
                        @if ($team->code)<span class="ix-ref">{{ $team->code }}</span>@endif
                        @if ($profile?->trading_name && $profile->trading_name !== $team->name)<span>trading as {{ $profile->trading_name }}</span>@endif
                        @if ($profile?->industry)<span>{{ $profile->industry }}</span>@endif
                    </p>
                </div>
                @if ($canEdit)
                    <a href="{{ route('business.edit', $team) }}" class="ix-btn ix-btn--ghost ix-biz__editbtn">
                        <span class="material-symbols-rounded" aria-hidden="true">edit</span>Edit profile
                    </a>
                @endif
            </figcaption>
        </figure>

        <div class="ix-biz__cols">
            <div class="ix-biz__main">
                @php($sectionNo = 0)
                @if ($profile?->description)
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ str_pad((string) ++$sectionNo, 2, '0', STR_PAD_LEFT) }}</span><h2 class="ix-bizsec__lbl">What this business does</h2></div>
                        <p class="ix-biz__prose">{{ $profile->description }}</p>
                    </section>
                @endif

                @if ($profile?->products_services)
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ str_pad((string) ++$sectionNo, 2, '0', STR_PAD_LEFT) }}</span><h2 class="ix-bizsec__lbl">Products and services</h2></div>
                        <p class="ix-biz__prose">{{ $profile->products_services }}</p>
                    </section>
                @endif

                @if ($gallery->isNotEmpty())
                    <section class="ix-bizsec">
                        <div class="ix-bizsec__head"><span class="ix-bizsec__num">{{ str_pad((string) ++$sectionNo, 2, '0', STR_PAD_LEFT) }}</span><h2 class="ix-bizsec__lbl">In the yard</h2></div>
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

                @if ($questions->isNotEmpty() || $solutions->isNotEmpty())
                    <section class="ix-fieldblock">
                        <div class="ix-fieldblock__head"><span class="ix-fieldblock__num">{{ str_pad((string) ++$sectionNo, 2, '0', STR_PAD_LEFT) }}</span><h2 class="ix-fieldblock__lbl">On InfoDot</h2></div>
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

                @if (! $profile?->hasContent() && $gallery->isEmpty() && $questions->isEmpty() && $solutions->isEmpty())
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

                @if ($profile?->website || $profile?->contact_email || $profile?->contact_phone || $links)
                    <section class="ix-biz__card">
                        <h2>Reach them</h2>
                        <dl class="ix-biz__facts">
                            @if ($profile?->website)<div><dt>Website</dt><dd><a href="{{ $profile->website }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $profile->website), 40) }}</a></dd></div>@endif
                            @if ($profile?->contact_email)<div><dt>Email</dt><dd><a href="mailto:{{ $profile->contact_email }}">{{ $profile->contact_email }}</a></dd></div>@endif
                            @if ($profile?->contact_phone)<div><dt>Phone</dt><dd>{{ $profile->contact_phone }}</dd></div>@endif
                            @foreach ($links as $key => $url)
                                <div><dt>{{ ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube'][$key] ?? ucfirst($key) }}</dt><dd><a href="{{ $url }}" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $url), 40) }}</a></dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                <p class="ix-biz__note">This page is the business's own description of itself. InfoDot business verification arrives as a separate, evidence-based step — nothing here is a verified claim yet.</p>
            </aside>
        </div>
    </div>
</x-app-layout>

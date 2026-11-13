<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Business profile</h1>
            <p class="ix-lede">How <b>{{ $team->name }}</b> shows up in the directory and on its own page. <a href="{{ route('business.show', $team) }}">Open the business page</a>, <a href="{{ route('business.show', [$team, 'as' => 'visitor']) }}">preview it as a visitor</a>, or see <a href="{{ route('business.insights', $team) }}">what it is doing for you</a>.</p>
        </div>
    </div>

    @if ($team->isRestricted())
        <div class="ix-previewbar" role="status"><p><b>{{ $team->name }} is out of the directory while InfoDot reviews it.</b> Your changes are saved as usual.</p><a class="ix-btn ix-btn--sm" href="{{ route('business.standing', $team) }}">Why, and how to respond</a></div>
    @endif

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="ix-settings">
        <nav class="ix-settings__index" aria-label="Profile sections">
            @foreach ([['directory', 'Directory listing'], ['logo', 'Logo'], ['cover', 'Cover photograph'], ['about', 'About the business'], ['facts', 'Registry facts'], ['contact', 'Contact and links'], ['services', 'Services and products'], ['portfolio', 'Portfolio'], ['locations', 'Locations'], ['announcements', 'Announcements'], ['gallery', 'Gallery'], ['brochures', 'Brochures'], ['managers', 'Who manages this'], ['changes', 'Recent changes'], ['standing', 'Standing and appeals'], ['featured', 'Featured placement']] as $i => [$id, $label])
                <a href="#{{ $id }}"><span class="ix-settings__no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ix-settings__body">
            <section id="directory" class="ix-settings__sec">
                <h2>Directory listing</h2>
                <p class="ix-hint">Who can find {{ $team->name }} in <a href="{{ route('businesses.index') }}">Businesses</a> and open its page. Your team always can.</p>
                @php
                    $listing = $team->listing();
                @endphp
                @if ($listing['listed'])
                    <p class="ix-alert" role="note"><b>Listed.</b> {{ $listing['audience'] === \App\Models\TeamProfile::PUBLIC ? 'Anyone can find '.$team->name.' in Businesses, signed in or not.' : 'People signed in to InfoDot can find '.$team->name.' in Businesses.' }}</p>
                @else
                    <div class="ix-alert" role="note">
                        <p><b>Not listed yet.</b> To put {{ $team->name }} in Businesses:</p>
                        <ul class="list-disc pl-5">
                            @if (in_array('visibility', $listing['missing'], true))<li>Choose Anyone or InfoDot users below. At the moment only your own team can see it.</li>@endif
                            @if (in_array('description', $listing['missing'], true))<li>Say what the business does, in the box below.</li>@endif
                            @if (in_array('restricted', $listing['missing'], true))<li>InfoDot is reviewing the business. <a href="{{ route('business.standing', $team) }}">Why, and how to respond</a>.</li>@endif
                        </ul>
                    </div>
                @endif
                <form method="post" action="{{ route('business.directory', $team) }}" class="ix-form">
                    @csrf
                    <fieldset class="ix-choice">
                        <legend class="ix-label">Who can see the business</legend>
                        @foreach (\App\Models\TeamProfile::VISIBILITY as $value => $label)
                            <label class="ix-choice__opt">
                                <input type="radio" name="visibility" value="{{ $value }}" @checked(old('visibility', $profile->visibility ?? \App\Models\TeamProfile::PRIVATE) === $value)>
                                <span><b>{{ \Illuminate\Support\Str::before($label, ' — ') }}</b><span class="ix-hint">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($label, ' — ')) }}</span></span>
                            </label>
                        @endforeach
                    </fieldset>
                    <p class="ix-hint">Whatever you choose, InfoDot never shows your team’s own accounts, personal numbers or staff contact details. People reach the business through the details you publish below.</p>

                    <div class="ix-form__row"><label class="ix-label" for="description">What the business does <span class="ix-hint">a business is listed once it says this</span></label><textarea class="ix-textarea" id="description" name="description" rows="5" maxlength="2000">{{ old('description', $profile->description) }}</textarea></div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="business_type">Type of business</label>
                        <select class="ix-input" id="business_type" name="business_type">
                            <option value="">Not stated</option>
                            @foreach (\App\Models\TeamProfile::BUSINESS_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('business_type', $profile->business_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <fieldset class="ix-choice">
                        <legend class="ix-label">Categories <span class="ix-hint">up to three — where a customer would look first</span></legend>
                        <div class="ix-choice__grid">
                            @foreach ($categories as $c)
                                <label class="ix-choice__chk"><input type="checkbox" name="categories[]" value="{{ $c->id }}" @checked(in_array($c->id, old('categories', $chosen)))> {{ $c->name }}</label>
                            @endforeach
                        </div>
                    </fieldset>

                    <input type="hidden" name="accepts_enquiries" value="0">
                    <label class="ix-choice__chk"><input type="checkbox" name="accepts_enquiries" value="1" @checked(old('accepts_enquiries', $profile->accepts_enquiries ?? true))> Let people message the business through InfoDot (<a href="{{ route('business.inbox', $team) }}">the inbox</a>)</label>

                    <button type="submit" class="ix-btn ix-btn--primary">Save the listing</button>
                </form>
            </section>

            <section id="logo" class="ix-settings__sec">
                <h2>Logo</h2>
                <p class="ix-hint">Square, at least 128×128. It stands for the business everywhere its name shows. Replacing it keeps the old one on the record.</p>
                <div class="ix-biz__mediarow">
                    <span class="ix-biz__logo ix-biz__logo--lg">
                        @if ($logo)<img src="{{ route('attachments.show', $logo) }}" alt="Current logo" width="96" height="96">@else{{ mb_substr(trim($team->name), 0, 1) }}@endif
                    </span>
                    <form method="post" action="{{ route('business.media', $team) }}" enctype="multipart/form-data" class="ix-form" data-crop-form data-crop-aspect="1">
                        @csrf
                        <input type="hidden" name="role" value="logo">
                        <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required class="ix-input" data-crop-file>
                        <div class="ix-crop" data-crop-frame hidden>
                            <div class="ix-crop__window" style="aspect-ratio: 1 / 1"><img alt="" data-crop-img draggable="false"></div>
                            <label class="ix-crop__zoom">Zoom <input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom></label>
                            <p class="ix-hint">Drag the picture to place it; zoom to crop tighter.</p>
                        </div>
                        <button type="submit" class="ix-btn ix-btn--primary">{{ $logo ? 'Replace the logo' : 'Put the logo up' }}</button>
                    </form>
                </div>
            </section>

            <section id="cover" class="ix-settings__sec">
                <h2>Cover photograph</h2>
                <p class="ix-hint">The wide picture across the top of the page: the premises, the workshop, the team, the work itself. At least 800×250.</p>
                @if ($cover)
                    <img class="ix-biz__coverprev" src="{{ route('attachments.show', $cover) }}" alt="Current cover photograph">
                @endif
                <form method="post" action="{{ route('business.media', $team) }}" enctype="multipart/form-data" class="ix-form" data-crop-form data-crop-aspect="3.2">
                    @csrf
                    <input type="hidden" name="role" value="cover">
                    <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required class="ix-input" data-crop-file>
                    <div class="ix-crop" data-crop-frame hidden>
                        <div class="ix-crop__window" style="aspect-ratio: 16 / 5"><img alt="" data-crop-img draggable="false"></div>
                        <label class="ix-crop__zoom">Zoom <input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom></label>
                        <p class="ix-hint">Drag the picture to place it; zoom to crop tighter.</p>
                    </div>
                    <button type="submit" class="ix-btn ix-btn--primary">{{ $cover ? 'Replace the cover' : 'Put the cover up' }}</button>
                </form>
            </section>

            <section id="about" class="ix-settings__sec">
                <h2>About the business</h2>
                <form method="post" action="{{ route('business.update', $team) }}" class="ix-form">
                    @csrf
                    <div class="ix-form__row"><label class="ix-label" for="trading_name">Trading name</label><input class="ix-input" id="trading_name" name="trading_name" maxlength="160" value="{{ old('trading_name', $profile->trading_name) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="industry">Industry</label><input class="ix-input" id="industry" name="industry" maxlength="80" value="{{ old('industry', $profile->industry) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="category">Category</label><input class="ix-input" id="category" name="category" maxlength="80" value="{{ old('category', $profile->category) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="industries_served">Industries served <span class="ix-hint">who your customers are</span></label><input class="ix-input" id="industries_served" name="industries_served" maxlength="300" value="{{ old('industries_served', $profile->industries_served) }}" placeholder="For example: Mining, agriculture, local government"></div>
                    <div class="ix-form__row"><label class="ix-label" for="products_services">More about what you offer <span class="ix-hint">optional; list each offering under Services and products</span></label><textarea class="ix-textarea" id="products_services" name="products_services" rows="3" maxlength="2000">{{ old('products_services', $profile->products_services) }}</textarea></div>

                    <h2 id="facts">Registry facts</h2>
                    <div class="ix-form__row"><label class="ix-label" for="location">Location</label><input class="ix-input" id="location" name="location" maxlength="160" value="{{ old('location', $profile->location) }}" placeholder="Town or city, province"></div>
                    <div class="ix-form__row"><label class="ix-label" for="regions">Service areas</label><input class="ix-input" id="regions" name="regions" maxlength="160" value="{{ old('regions', $profile->regions) }}" placeholder="Where you take work: provinces, towns or regions"></div>
                    <div class="ix-form__row"><label class="ix-label" for="registration_number">Business registration number</label><input class="ix-input" id="registration_number" name="registration_number" maxlength="60" value="{{ old('registration_number', $profile->registration_number) }}" placeholder="Where applicable"></div>
                    <div class="ix-form__row"><label class="ix-label" for="registration_country">Country of registration</label><input class="ix-input" id="registration_country" name="registration_country" maxlength="60" value="{{ old('registration_country', $profile->registration_country) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="established_on">Established</label><input class="ix-input" type="date" id="established_on" name="established_on" max="{{ now()->toDateString() }}" value="{{ old('established_on', $profile->established_on?->toDateString()) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="size">Business size</label><input class="ix-input" id="size" name="size" maxlength="40" value="{{ old('size', $profile->size) }}" placeholder="For example: 1–10 people"></div>

                    <h2 id="contact">Contact and links</h2>
                    <p class="ix-hint">Only the business’s own details belong here, never a person’s private number. Untick a detail to keep it on record but off the business page.</p>
                    @foreach ([['website', 'website', 'Website', 'url', 'https://'], ['email', 'contact_email', 'Business email', 'email', ''], ['phone', 'contact_phone', 'Business phone', 'text', '']] as [$key, $field, $label, $type, $ph])
                        <div class="ix-form__row">
                            <label class="ix-label" for="{{ $field }}">{{ $label }}</label>
                            <div class="ix-contactrow">
                                <input class="ix-input" type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" maxlength="{{ $key === 'phone' ? 40 : 255 }}" value="{{ old($field, $profile->{$field}) }}" @if($ph) placeholder="{{ $ph }}" @endif>
                                <input type="hidden" name="public_contact[{{ $key }}]" value="0">
                                <label class="ix-choice__chk"><input type="checkbox" name="public_contact[{{ $key }}]" value="1" @checked(old('public_contact.'.$key, $profile->shows($key)))> Show on the page</label>
                            </div>
                        </div>
                    @endforeach
                    @foreach (\App\Models\TeamProfile::LINK_KEYS as $key)
                        <div class="ix-form__row"><label class="ix-label" for="links_{{ $key }}">{{ ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube'][$key] }}</label><input class="ix-input" type="url" id="links_{{ $key }}" name="links[{{ $key }}]" maxlength="255" value="{{ old('links.'.$key, $profile->links[$key] ?? '') }}" placeholder="https://"></div>
                    @endforeach
                    <input type="hidden" name="public_contact[links]" value="0">
                    <label class="ix-choice__chk"><input type="checkbox" name="public_contact[links]" value="1" @checked(old('public_contact.links', $profile->shows('links')))> Show these links on the page</label>

                    <button type="submit" class="ix-btn ix-btn--primary">Save the profile</button>
                </form>
            </section>

            <section id="services" class="ix-settings__sec">
                <h2>Services and products</h2>
                <p class="ix-hint">One entry for each thing a customer can come to you for. The directory searches them on their own, so “Borehole drilling” finds you even when the description does not say it. A price shows only if you write one.</p>
                @if ($services->isNotEmpty())
                    <ul class="ix-svclist">
                        @foreach ($services as $service)
                            @php
                                $pic = $offeringPictures->get($service->id)?->last();
                            @endphp
                            <li>
                                <div class="ix-svclist__item">
                                    @if ($pic)<img src="{{ route('attachments.show', $pic) }}" alt="" width="56" height="42">@endif
                                    <div>
                                        <b>{{ $service->name }}</b> <span class="ix-chip">{{ \App\Models\BusinessService::KINDS[$service->kind] ?? 'Service' }}</span>
                                        @if ($service->description)<p class="ix-hint">{{ $service->description }}</p>@endif
                                        @if ($service->price_note)<p class="ix-hint">Price: {{ $service->price_note }}</p>@endif
                                    </div>
                                </div>
                                <div class="ix-svclist__tools">
                                    <details class="ix-itemedit">
                                        <summary class="ix-btn ix-btn--ghost ix-btn--sm">Edit</summary>
                                        <form method="post" action="{{ route('business.services.update', [$team, $service]) }}" enctype="multipart/form-data" class="ix-form">
                                            @csrf
                                            @include('business._offering-fields', ['s' => $service, 'sfx' => $service->id])
                                            @if ($pic)<label class="ix-choice__chk"><input type="checkbox" name="remove_image" value="1"> Take the picture down</label>@endif
                                            <button type="submit" class="ix-btn ix-btn--primary ix-btn--sm">Save</button>
                                        </form>
                                    </details>
                                    <form method="post" action="{{ route('business.services.destroy', [$team, $service]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm" aria-label="Remove {{ $service->name }}">Remove</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.services.store', $team) }}" enctype="multipart/form-data" class="ix-form">
                    @csrf
                    @include('business._offering-fields', ['s' => null, 'sfx' => 'new'])
                    <button type="submit" class="ix-btn">Add it</button>
                </form>
            </section>

            <section id="portfolio" class="ix-settings__sec">
                <h2>Portfolio</h2>
                <p class="ix-hint">Work you have done, with photographs of the real thing. Name a client only if they agreed to it.</p>
                @if ($projects->isNotEmpty())
                    <ul class="ix-svclist">
                        @foreach ($projects as $project)
                            @php
                                $pics = $projectPictures->get($project->id) ?? collect();
                            @endphp
                            <li>
                                <div>
                                    <b>{{ $project->title }}</b>@if ($project->year) <span class="ix-hint">{{ $project->year }}</span>@endif
                                    @if ($project->summary)<p class="ix-hint">{{ \Illuminate\Support\Str::limit($project->summary, 160) }}</p>@endif
                                    @if ($pics->isNotEmpty())
                                        <ul class="ix-thumbs">
                                            @foreach ($pics as $shot)
                                                <li>
                                                    <img src="{{ route('attachments.show', $shot) }}" alt="{{ $shot->caption ?: 'Project photograph' }}" width="96" height="72" loading="lazy">
                                                    <form method="post" action="{{ route('business.pictures.remove', [$team, $shot]) }}">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm" aria-label="Take this picture down">Take down</button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="ix-svclist__tools">
                                    <details class="ix-itemedit">
                                        <summary class="ix-btn ix-btn--ghost ix-btn--sm">Edit or add pictures</summary>
                                        <form method="post" action="{{ route('business.projects.update', [$team, $project]) }}" enctype="multipart/form-data" class="ix-form">
                                            @csrf
                                            @include('business._project-fields', ['p' => $project, 'sfx' => $project->id])
                                            <button type="submit" class="ix-btn ix-btn--primary ix-btn--sm">Save</button>
                                        </form>
                                    </details>
                                    <form method="post" action="{{ route('business.projects.destroy', [$team, $project]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm" aria-label="Remove {{ $project->title }}">Remove</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.projects.store', $team) }}" enctype="multipart/form-data" class="ix-form">
                    @csrf
                    @include('business._project-fields', ['p' => null, 'sfx' => 'new'])
                    <button type="submit" class="ix-btn">Add the project</button>
                </form>
            </section>

            <section id="locations" class="ix-settings__sec">
                <h2>Locations</h2>
                <p class="ix-hint">Offices, branches, workshops or stores. The street address shows only where you tick it; otherwise people see the town and province. Searches by town find these too.</p>
                @if ($locations->isNotEmpty())
                    <ul class="ix-svclist">
                        @foreach ($locations as $loc)
                            <li>
                                <div><b>{{ $loc->label }}</b><p class="ix-hint">{{ collect([$loc->address, $loc->city, $loc->province, $loc->postal_code])->filter()->implode(', ') }} — {{ $loc->show_address ? 'address shown' : 'town and province only' }}</p></div>
                                <div class="ix-svclist__tools">
                                    <details class="ix-itemedit">
                                        <summary class="ix-btn ix-btn--ghost ix-btn--sm">Edit</summary>
                                        <form method="post" action="{{ route('business.locations.update', [$team, $loc]) }}" class="ix-form">
                                            @csrf
                                            @include('business._location-fields', ['l' => $loc, 'sfx' => $loc->id])
                                            <button type="submit" class="ix-btn ix-btn--primary ix-btn--sm">Save</button>
                                        </form>
                                    </details>
                                    <form method="post" action="{{ route('business.locations.destroy', [$team, $loc]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm" aria-label="Remove {{ $loc->label }}">Remove</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.locations.store', $team) }}" class="ix-form">
                    @csrf
                    @include('business._location-fields', ['l' => null, 'sfx' => 'new'])
                    <button type="submit" class="ix-btn">Add the location</button>
                </form>
            </section>

            <section id="announcements" class="ix-settings__sec">
                <h2>Announcements</h2>
                <p class="ix-hint">News for people who look you up: a new branch, a product, holiday hours. Up to five show at once, newest first. Withdrawing one takes it off the page.</p>
                @if ($announcements->isNotEmpty())
                    <ul class="ix-svclist">
                        @foreach ($announcements as $a)
                            <li>
                                <div>
                                    <b>{{ $a->title }}</b>
                                    <p class="ix-hint">{{ $a->published_at->format('j M Y') }} — @if ($a->isShowing())showing{{ $a->ends_at ? ' until '.$a->ends_at->format('j M Y') : '' }}@else off the page since {{ $a->ends_at?->format('j M Y') }}@endif</p>
                                </div>
                                @if ($a->isShowing())
                                    <form method="post" action="{{ route('business.announcements.withdraw', [$team, $a]) }}">
                                        @csrf
                                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm">Withdraw</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.announcements.store', $team) }}" class="ix-form">
                    @csrf
                    <div class="ix-form__row"><label class="ix-label" for="a_title">Headline</label><input class="ix-input" id="a_title" name="title" minlength="3" maxlength="140" required></div>
                    <div class="ix-form__row"><label class="ix-label" for="a_body">The news</label><textarea class="ix-textarea" id="a_body" name="body" rows="3" minlength="10" maxlength="1500" required></textarea></div>
                    <div class="ix-form__row"><label class="ix-label" for="a_ends">Take it down on <span class="ix-hint">optional</span></label><input class="ix-input" type="date" id="a_ends" name="ends_at" min="{{ now()->addDay()->toDateString() }}"></div>
                    <button type="submit" class="ix-btn">Publish</button>
                </form>
            </section>

            <section id="gallery" class="ix-settings__sec">
                <h2>Gallery</h2>
                <p class="ix-hint">Photographs of the real thing: premises, products, projects, people at work. A caption says what each one shows. Taking one down keeps it on the record.</p>
                @if ($gallery->isNotEmpty())
                    <ul class="ix-biz__gallery ix-biz__gallery--manage">
                        @foreach ($gallery as $shot)
                            <li>
                                <figure>
                                    <img src="{{ route('attachments.show', $shot) }}" alt="{{ $shot->caption ?: 'Gallery photograph' }}" loading="lazy">
                                    @if ($shot->caption)<figcaption>{{ $shot->caption }}</figcaption>@endif
                                </figure>
                                <form method="post" action="{{ route('business.media.remove', [$team, $shot]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm">Take down</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.media', $team) }}" enctype="multipart/form-data" class="ix-form">
                    @csrf
                    <input type="hidden" name="role" value="gallery">
                    <div class="ix-form__row"><label class="ix-label" for="gallery_file">Photograph</label><input class="ix-input" type="file" id="gallery_file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required></div>
                    <div class="ix-form__row"><label class="ix-label" for="gallery_caption">Caption</label><input class="ix-input" id="gallery_caption" name="caption" maxlength="500" placeholder="What this shows"></div>
                    <button type="submit" class="ix-btn ix-btn--primary">Add to the gallery</button>
                </form>
            </section>

            <section id="brochures" class="ix-settings__sec">
                <h2>Brochures</h2>
                <p class="ix-hint">PDFs people can open from your page: a company profile, a price list, a product sheet. Up to five, each at most {{ round((int) config('attachments.kinds.pdf.max_bytes') / 1048576) }} MB. Taking one down keeps it on the record.</p>
                @if ($brochures->isNotEmpty())
                    <ul class="ix-svclist">
                        @foreach ($brochures as $doc)
                            <li>
                                <div><a href="{{ route('attachments.show', $doc) }}" target="_blank" rel="noopener"><b>{{ $doc->caption ?: $doc->name }}</b></a> <span class="ix-hint">PDF, {{ $doc->sizeLabel() }}</span></div>
                                <form method="post" action="{{ route('business.pictures.remove', [$team, $doc]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm" aria-label="Take down {{ $doc->caption }}">Take down</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('business.brochures.store', $team) }}" enctype="multipart/form-data" class="ix-form">
                    @csrf
                    <div class="ix-form__row"><label class="ix-label" for="b_title">Title</label><input class="ix-input" id="b_title" name="title" minlength="3" maxlength="140" required placeholder="For example: Company profile 2026"></div>
                    <div class="ix-form__row"><label class="ix-label" for="b_file">PDF</label><input class="ix-input" type="file" id="b_file" name="file" accept="application/pdf" required></div>
                    <button type="submit" class="ix-btn">Add the brochure</button>
                </form>
            </section>

            <section id="managers" class="ix-settings__sec">
                <h2>Who manages this profile</h2>
                <p class="ix-hint">The owner and the business’s administrators can change everything on this page. Editors and other members cannot. Roles are set in the team settings.</p>
                <p><a class="ix-btn ix-btn--ghost" href="{{ route('teams.show', $team->id) }}"><span class="material-symbols-rounded" aria-hidden="true">group</span>Team members and roles</a></p>
            </section>

            <section id="standing" class="ix-settings__sec">
                <h2>Standing and appeals</h2>
                <p class="ix-hint">If InfoDot reviews the business, its explanation and your right to appeal are on the standing page.</p>
                <p><a class="ix-btn ix-btn--ghost" href="{{ route('business.standing', $team) }}"><span class="material-symbols-rounded" aria-hidden="true">gavel</span>Standing and appeals</a></p>
            </section>

            <section id="featured" class="ix-settings__sec">
                <h2>Featured placement</h2>
                <p class="ix-hint">A few businesses are shown at the top of the directory for up to {{ \App\Models\BusinessFeature::MAX_DAYS }} days. It is a placement, not a rating or a verification. <a href="{{ route('businesses.featured') }}">How featuring works</a>.</p>
                @if ($feature && $feature->status === 'active')
                    <p class="ix-alert" role="status">{{ $team->name }} is featured until {{ $feature->ends_at?->format('j M Y') }}.</p>
                @elseif ($feature)
                    <p class="ix-alert" role="status">You asked to be featured on {{ $feature->created_at->format('j M Y') }}. A reviewer will decide.</p>
                @else
                    @if ($lastFeature && $lastFeature->status === 'declined')<p class="ix-hint">Your last request was declined: {{ $lastFeature->decision_note }}</p>@endif
                    @if ($featureUnmet)
                        <p class="ix-hint">Before you can ask, the business needs to meet these:</p>
                        <ul class="ix-trust__checks">@foreach ($featureUnmet as $k)<li>{{ \App\Services\Directory\FeaturedPlacement::RULES[$k] }}</li>@endforeach</ul>
                    @else
                        <form method="post" action="{{ route('business.feature', $team) }}" class="ix-form">
                            @csrf
                            <div class="ix-form__row"><label class="ix-label" for="feature_reason">Anything the reviewer should know <span class="ix-hint">optional</span></label><textarea class="ix-textarea" id="feature_reason" name="reason" rows="2" maxlength="600"></textarea></div>
                            <button type="submit" class="ix-btn">Ask to be featured</button>
                        </form>
                    @endif
                @endif
            </section>

            <section id="changes" class="ix-settings__sec">
                <h2>Recent changes</h2>
                <p class="ix-hint">Every change to the business’s public presence is recorded with who made it. This record cannot be edited.</p>
                @if ($events->isEmpty())
                    <p class="ix-hint">Nothing recorded yet.</p>
                @else
                    <ol class="ix-ledger">
                        @foreach ($events as $e)
                            <li>
                                <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->format('j M Y, H:i') }}</time>
                                <span><b>{{ $e->user?->name ?? 'Someone no longer on InfoDot' }}</b> {{ mb_strtolower($e->label()) }}@if ($e->detail): {{ $e->detail }}@endif</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Business profile</h1>
            <p class="ix-lede">What everyone on InfoDot sees when they look at <b>{{ $team->name }}</b>. <a href="{{ route('business.show', $team) }}">Open the public page</a>.</p>
        </div>
    </div>

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
            @foreach ([['logo', 'Logo'], ['cover', 'Cover photograph'], ['about', 'About the business'], ['facts', 'Registry facts'], ['contact', 'Contact and links'], ['gallery', 'Gallery']] as $i => [$id, $label])
                <a href="#{{ $id }}"><span class="ix-settings__no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ix-settings__body">
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
                    <div class="ix-form__row"><label class="ix-label" for="description">What the business does</label><textarea class="ix-input" id="description" name="description" rows="5" maxlength="2000">{{ old('description', $profile->description) }}</textarea></div>
                    <div class="ix-form__row"><label class="ix-label" for="industry">Industry</label><input class="ix-input" id="industry" name="industry" maxlength="80" value="{{ old('industry', $profile->industry) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="category">Category</label><input class="ix-input" id="category" name="category" maxlength="80" value="{{ old('category', $profile->category) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="products_services">Products and services</label><textarea class="ix-input" id="products_services" name="products_services" rows="4" maxlength="2000">{{ old('products_services', $profile->products_services) }}</textarea></div>

                    <h2 id="facts">Registry facts</h2>
                    <div class="ix-form__row"><label class="ix-label" for="location">Location</label><input class="ix-input" id="location" name="location" maxlength="160" value="{{ old('location', $profile->location) }}" placeholder="Town or city, province"></div>
                    <div class="ix-form__row"><label class="ix-label" for="regions">Operating regions</label><input class="ix-input" id="regions" name="regions" maxlength="160" value="{{ old('regions', $profile->regions) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="registration_number">Business registration number</label><input class="ix-input" id="registration_number" name="registration_number" maxlength="60" value="{{ old('registration_number', $profile->registration_number) }}" placeholder="Where applicable"></div>
                    <div class="ix-form__row"><label class="ix-label" for="registration_country">Country of registration</label><input class="ix-input" id="registration_country" name="registration_country" maxlength="60" value="{{ old('registration_country', $profile->registration_country) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="established_on">Established</label><input class="ix-input" type="date" id="established_on" name="established_on" max="{{ now()->toDateString() }}" value="{{ old('established_on', $profile->established_on?->toDateString()) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="size">Business size</label><input class="ix-input" id="size" name="size" maxlength="40" value="{{ old('size', $profile->size) }}" placeholder="For example: 1–10 people"></div>

                    <h2 id="contact">Contact and links</h2>
                    <div class="ix-form__row"><label class="ix-label" for="website">Website</label><input class="ix-input" type="url" id="website" name="website" maxlength="255" value="{{ old('website', $profile->website) }}" placeholder="https://"></div>
                    <div class="ix-form__row"><label class="ix-label" for="contact_email">Business email</label><input class="ix-input" type="email" id="contact_email" name="contact_email" maxlength="255" value="{{ old('contact_email', $profile->contact_email) }}"></div>
                    <div class="ix-form__row"><label class="ix-label" for="contact_phone">Business phone</label><input class="ix-input" id="contact_phone" name="contact_phone" maxlength="40" value="{{ old('contact_phone', $profile->contact_phone) }}"></div>
                    @foreach (\App\Models\TeamProfile::LINK_KEYS as $key)
                        <div class="ix-form__row"><label class="ix-label" for="links_{{ $key }}">{{ ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'youtube' => 'YouTube'][$key] }}</label><input class="ix-input" type="url" id="links_{{ $key }}" name="links[{{ $key }}]" maxlength="255" value="{{ old('links.'.$key, $profile->links[$key] ?? '') }}" placeholder="https://"></div>
                    @endforeach

                    <button type="submit" class="ix-btn ix-btn--primary">Save the profile</button>
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
        </div>
    </div>
</x-app-layout>

@php
    $another = $another ?? false;
    $importId = $importId ?? null;
    $p = $prefill ?? [];
    $site = $siteHost ?? null;
    $isDoc = ($importSource ?? null) === 'document';
@endphp
<x-guest-layout :title="$another ? 'Create another company' : 'Set up your company'">
    <x-jet-authentication-card>
        @if (request()->boolean('verified'))
            <p class="ix-alert" role="status">Your email address is confirmed.</p>
        @endif

        <h1 class="ix-auth-title">{{ $another ? 'Create another company' : 'Set up your company' }}</h1>
        <p class="ix-entry__lede">
            @if ($another)
                Each company has its own people, pages and platforms. Your login stays the same, and you can switch between companies from the menu.
            @elseif ($branding)
                {{ $branding->name }} is for businesses. Your account is ready; name your business to continue to {{ $branding->name }}.
            @else
                Your account is ready. Your company is what you manage on InfoDot: its page, its people and its platforms. A name and a sentence about what it does is enough to start. You can add the rest later.
            @endif
        </p>

        @if ($errors->any())
            <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        @if ($importId)
            <p class="ix-alert" role="status">We read {{ $isDoc ? 'the document '.$site : $site }} and filled in what {{ $isDoc ? 'it says' : 'it publishes' }}. Check every line and change anything that is wrong: nothing is saved until you create the company, and nothing here is treated as verified.</p>
        @else
            <section aria-labelledby="site-h">
                <h2 id="site-h" class="ix-label">Have a website? Let us read it.</h2>
                <p class="ix-hint">Type its address and we fill in what we can find. You check it before anything is saved. We only read what the site publishes and we follow its robots.txt.</p>
                <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.website') }}">
                    @csrf
                    @if ($another)<input type="hidden" name="another" value="1">@endif
                    <div class="ix-field">
                        <x-jet-label for="website_url" value="Website address" />
                        <x-jet-input id="website_url" type="text" name="website" :value="old('website')" maxlength="255" autocomplete="url" placeholder="yourcompany.co.za" />
                    </div>
                    <button type="submit" class="ix-btn ix-btn--ghost">Read my website</button>
                </form>
            </section>
            <section aria-labelledby="doc-h">
                <h2 id="doc-h" class="ix-label">Have a company document? Upload it.</h2>
                <p class="ix-hint">A PDF or text file such as a registration certificate or company profile, up to 5 MB. We read the text for the name, contact details and registration number, and you check it before anything is saved. We do not keep the file. Scanned pages and Word or Excel files cannot be read yet.</p>
                <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.document') }}" enctype="multipart/form-data">
                    @csrf
                    @if ($another)<input type="hidden" name="another" value="1">@endif
                    <div class="ix-field">
                        <x-jet-label for="document_file" value="Document" />
                        <input id="document_file" class="ix-input" type="file" name="document" accept=".pdf,.txt,application/pdf,text/plain">
                    </div>
                    <button type="submit" class="ix-btn ix-btn--ghost">Read my document</button>
                </form>
            </section>
            <p class="ix-hint">Or fill in the details yourself.</p>
        @endif

        <form class="ix-entry__form" method="POST" action="{{ route('onboarding.business.store') }}">
            @csrf
            @if ($another)<input type="hidden" name="another" value="1">@endif
            @if ($importId)<input type="hidden" name="import" value="{{ $importId }}">@endif

            <div class="ix-field">
                <x-jet-label for="name" value="Company name" />
                <x-jet-input id="name" type="text" name="name" :value="old('name', $p['name'] ?? '')" required autofocus maxlength="255" autocomplete="organization" />
                @isset($p['name'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}. Check it.</p>@endisset
            </div>

            <div class="ix-field">
                <x-jet-label for="industry" value="Industry" />
                <select id="industry" name="industry" class="ix-input ix-select">
                    <option value="">{{ __('General business') }}</option>
                    @foreach ($industries as $key => $label)
                        @continue($key === 'general')
                        <option value="{{ $key }}" @selected(old('industry') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
                @if ($importId)<p class="ix-hint">We never guess an industry. Choose one.</p>@endif
            </div>

            <div class="ix-field">
                <x-jet-label for="description" value="What does the business do?" />
                <textarea id="description" name="description" class="ix-textarea" rows="3" maxlength="600" required placeholder="A sentence or two is plenty.">{{ old('description', $p['description'] ?? '') }}</textarea>
                @isset($p['description'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}. Check it.</p>@endisset
            </div>

            @if ($importId)
                <fieldset class="ix-choice">
                    <legend class="ix-label">Details we found (all optional)</legend>
                    <div class="ix-field">
                        <x-jet-label for="website" value="Website" />
                        <x-jet-input id="website" type="text" name="website" :value="old('website', $p['website'] ?? '')" maxlength="255" />
                    </div>
                    <div class="ix-field">
                        <x-jet-label for="contact_email" value="Business email" />
                        <x-jet-input id="contact_email" type="email" name="contact_email" :value="old('contact_email', $p['contact_email'] ?? '')" maxlength="255" />
                        @isset($p['contact_email'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}.</p>@endisset
                    </div>
                    <div class="ix-field">
                        <x-jet-label for="contact_phone" value="Business phone" />
                        <x-jet-input id="contact_phone" type="text" name="contact_phone" :value="old('contact_phone', $p['contact_phone'] ?? '')" maxlength="40" />
                        @isset($p['contact_phone'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}.</p>@endisset
                    </div>
                    <div class="ix-field">
                        <x-jet-label for="location" value="Location" />
                        <x-jet-input id="location" type="text" name="location" :value="old('location', $p['location'] ?? '')" maxlength="160" />
                        @isset($p['location'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}.</p>@endisset
                    </div>
                    <div class="ix-field">
                        <x-jet-label for="registration_number" value="Registration number" />
                        <x-jet-input id="registration_number" type="text" name="registration_number" :value="old('registration_number', $p['registration_number'] ?? '')" maxlength="60" />
                        @isset($p['registration_number'])<p class="ix-hint">Read from {{ $isDoc ? 'your document' : $site }}. Nothing here is treated as verified.</p>@endisset
                    </div>
                    @foreach (\App\Models\TeamProfile::LINK_KEYS as $slot)
                        <div class="ix-field">
                            <x-jet-label for="link_{{ $slot }}" value="{{ ucfirst($slot) }} profile" />
                            <x-jet-input id="link_{{ $slot }}" type="url" name="links[{{ $slot }}]" :value="old('links.'.$slot, $p['links'][$slot] ?? '')" maxlength="255" />
                        </div>
                    @endforeach
                </fieldset>
            @endif

            <fieldset class="ix-choice">
                <legend class="ix-label">Who can see the business page</legend>
                @foreach ($visibility as $value => $label)
                    <label class="ix-choice__opt">
                        <input type="radio" name="visibility" value="{{ $value }}" @checked(old('visibility') === $value) required>
                        <span><b>{{ \Illuminate\Support\Str::before($label, ' — ') }}</b><span class="ix-hint">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($label, ' — ')) }}</span></span>
                    </label>
                @endforeach
                <p class="ix-hint">Nothing is chosen for you.</p>
            </fieldset>

            <details @if (old('stage') || old('size_band') || old('objectives')) open @endif>
                <summary class="ix-btn ix-btn--ghost">Tell us more (optional)</summary>
                <p class="ix-hint">This only helps us suggest the platforms that fit. Skip it if you like.</p>

                <div class="ix-field">
                    <x-jet-label for="stage" value="Where is the business today?" />
                    <select id="stage" name="stage" class="ix-input ix-select">
                        <option value="">Prefer not to say</option>
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}" @selected(old('stage') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ix-field">
                    <x-jet-label for="size_band" value="How many people work in it?" />
                    <select id="size_band" name="size_band" class="ix-input ix-select">
                        <option value="">Prefer not to say</option>
                        @foreach ($sizeBands as $key => $label)
                            <option value="{{ $key }}" @selected(old('size_band') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @include('onboarding._objectives', ['selected' => (array) old('objectives', [])])
            </details>

            <x-jet-button class="ix-entry__go">Create company and continue</x-jet-button>
        </form>

        @unless ($branding || $another)
            <p class="ix-entry__links"><a href="{{ route('dashboard') }}">Skip for now</a></p>
        @endunless
    </x-jet-authentication-card>
</x-guest-layout>

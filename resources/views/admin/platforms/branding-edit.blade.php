<x-app-layout :title="$platform->name.' sign-in branding'">
    @include('admin.platforms._tabs', ['current' => 'branding'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $platform->name }}</h1>
            <p class="ix-lede"><span class="ix-ref">{{ $platform->slug }}</span> Version {{ $row->version ?: 0 }}. Preview shows the result without saving; publish saves it, moves the version by one and keeps the reason in the audit trail.</p>
        </div>
        <a href="{{ route('admin.platforms.branding') }}" class="ix-btn ix-btn--ghost">All platforms</a>
    </div>

    @include('admin.platforms._flash')

    <section class="ix-panel" aria-labelledby="preview-h">
        <div class="ix-panel__head"><h2 id="preview-h">{{ $preview ? 'Preview (not saved)' : 'As people see it now' }}</h2></div>
        <div class="ix-panel__body">
            @include('admin.platforms._branding-preview', ['view' => $view])
        </div>
    </section>

    <form method="post" action="{{ route('admin.platforms.branding.publish', $platform) }}" enctype="multipart/form-data" class="ix-form ix-vgap">
        @csrf
        <section class="ix-panel" aria-labelledby="words-h">
            <div class="ix-panel__head"><h2 id="words-h">Words</h2></div>
            <div class="ix-panel__body">
                @php $v = fn (string $k) => old($k, $row->{$k}); @endphp
                <div class="ix-field"><label class="ix-label" for="b-name">Display name</label><input id="b-name" name="display_name" class="ix-input" maxlength="80" value="{{ $v('display_name') }}"></div>
                <div class="ix-field"><label class="ix-label" for="b-hl">Sign-in headline</label><input id="b-hl" name="headline_login" class="ix-input" maxlength="120" value="{{ $v('headline_login') }}"><p class="ix-hint">Empty uses "Welcome back to {{ $view->name }}".</p></div>
                <div class="ix-field"><label class="ix-label" for="b-hr">Registration headline</label><input id="b-hr" name="headline_register" class="ix-input" maxlength="120" value="{{ $v('headline_register') }}"><p class="ix-hint">Empty uses "Get started with {{ $view->name }}". Say only what the platform really does.</p></div>
                <div class="ix-field"><label class="ix-label" for="b-desc">Description</label><textarea id="b-desc" name="description" class="ix-textarea" rows="2" maxlength="300">{{ $v('description') }}</textarea><p class="ix-hint">Empty uses the catalogue tagline.</p></div>
                <div class="ix-field"><label class="ix-label" for="b-promo">Promotional line (optional)</label><input id="b-promo" name="promo_text" class="ix-input" maxlength="200" value="{{ $v('promo_text') }}"></div>
                <div class="ix-form__row">
                    <div class="ix-field"><label class="ix-label" for="b-cl">Sign-in button</label><input id="b-cl" name="cta_login" class="ix-input" maxlength="40" value="{{ $v('cta_login') }}"></div>
                    <div class="ix-field"><label class="ix-label" for="b-cr">Register button</label><input id="b-cr" name="cta_register" class="ix-input" maxlength="40" value="{{ $v('cta_register') }}"></div>
                </div>
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="look-h">
            <div class="ix-panel__head"><h2 id="look-h">Colour and mode</h2></div>
            <div class="ix-panel__body">
                <div class="ix-form__row">
                    @foreach (['primary_color' => 'Primary', 'secondary_color' => 'Secondary', 'accent_color' => 'Accent'] as $k => $label)
                        <div class="ix-field"><label class="ix-label" for="b-{{ $k }}">{{ $label }} (#rrggbb)</label><input id="b-{{ $k }}" name="{{ $k }}" class="ix-input" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#1d5fbf" value="{{ $v($k) }}"></div>
                    @endforeach
                </div>
                <div class="ix-field"><label class="ix-label" for="b-mode">Mode</label>
                    <select id="b-mode" name="mode" class="ix-select"><option value="light" @selected($v('mode') === 'light')>Light</option><option value="dark" @selected($v('mode') === 'dark')>Dark</option></select>
                </div>
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="img-h">
            <div class="ix-panel__head"><h2 id="img-h">Images</h2></div>
            <div class="ix-panel__body">
                <p class="ix-hint">PNG, JPEG or WebP only; SVG is not accepted. Use the platform's own approved logo and real screens, never stock images. Files are checked, then re-encoded.</p>
                <div class="ix-field"><label class="ix-label" for="b-logo">Logo (64 to 2400 px, up to 512 KB)</label><input id="b-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="ix-input">@if ($row->logo_path)<label><input type="checkbox" name="remove_logo" value="1"> Remove the uploaded logo</label>@endif</div>
                <div class="ix-field"><label class="ix-label" for="b-hero">Hero image (800x500 to 3840x2560, up to 2 MB)</label><input id="b-hero" name="hero" type="file" accept="image/png,image/jpeg,image/webp" class="ix-input">@if ($row->hero_path)<label><input type="checkbox" name="remove_hero" value="1"> Remove the hero image</label>@endif</div>
                <div class="ix-field"><label class="ix-label" for="b-alt">Hero image description</label><input id="b-alt" name="hero_alt" class="ix-input" maxlength="200" value="{{ $v('hero_alt') }}"><p class="ix-hint">Empty marks the image as decoration.</p></div>
                <div class="ix-field"><label class="ix-label" for="b-bg">Background image (same limits as the hero)</label><input id="b-bg" name="background" type="file" accept="image/png,image/jpeg,image/webp" class="ix-input">@if ($row->background_path)<label><input type="checkbox" name="remove_background" value="1"> Remove the background image</label>@endif</div>
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="dom-h">
            <div class="ix-panel__head"><h2 id="dom-h">Approved redirect domains</h2></div>
            <div class="ix-panel__body">
                <div class="ix-field"><label class="ix-label" for="b-dom">Domains (one per line)</label>
                    <textarea id="b-dom" name="approved_redirect_domains" class="ix-textarea" rows="2">{{ old('approved_redirect_domains', implode("\n", (array) $row->approved_redirect_domains)) }}</textarea>
                    <p class="ix-hint">Only the platform's own address or its subdomains are accepted. This list never changes where a sign-in returns: that is fixed by the registered redirect addresses.</p></div>
                <label><input type="checkbox" name="active" value="1" @checked(old('active', $row->exists ? $row->active : true))> Use this branding on the sign-in pages</label>
            </div>
        </section>

        <section class="ix-panel" aria-labelledby="pub-h">
            <div class="ix-panel__head"><h2 id="pub-h">Publish</h2></div>
            <div class="ix-panel__body">
                @include('admin.platforms._reason', ['id' => 'branding-reason'])
                <div class="ix-entry__row">
                    <button type="submit" class="ix-btn ix-btn--primary">Publish</button>
                    <button type="submit" class="ix-btn" formaction="{{ route('admin.platforms.branding.preview', $platform) }}" formnovalidate>Preview without saving</button>
                </div>
            </div>
        </section>
    </form>

    <section class="ix-panel" aria-labelledby="trail-h">
        <div class="ix-panel__head"><h2 id="trail-h">History</h2></div>
        <div class="ix-panel__body">@include('admin.platforms._trail', ['events' => $events])</div>
    </section>
</x-app-layout>

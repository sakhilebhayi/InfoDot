<x-app-layout title="Notification settings">
    @php($flash = session('notices_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Notification settings</h1>
            <p class="ix-lede">Every notification is kept on your <a href="{{ route('notices') }}">notifications page</a>. Here you choose which ones also pop up while you are using InfoDot, and how email reaches you. Account protection is always on.</p>
        </div>
    </div>

    @if ($flash)
        <p class="ix-alert" role="status">{{ $flash }}</p>
    @endif

    <form method="POST" action="{{ route('notices.preferences.update') }}" class="ix-prefs">
        @csrf

        @foreach ($categories as $key => $meta)
            @php($c = $chosen[$key])
            <section class="ix-panel ix-prefs__row" aria-labelledby="pref-{{ $key }}">
                <div class="ix-panel__body">
                    <h2 class="ix-h2" id="pref-{{ $key }}">{{ $meta['name'] }}</h2>
                    <p class="ix-hint">{{ $meta['about'] }}</p>

                    @if ($c['locked'])
                        <p class="ix-prefs__locked">Always on. This cannot be switched off.</p>
                    @else
                        <div class="ix-form__row">
                            @if ($meta['popup_locked'])
                                <p class="ix-prefs__locked">Always pops up: it is something to answer.</p>
                            @else
                                <x-form.switch :name="'prefs['.$key.'][popup]'" :id="'popup-'.$key" label="Pop up while I am using InfoDot" :checked="$c['popup']" />
                            @endif

                            @if ($meta['modes'] !== [])
                                <x-form.field :name="'prefs.'.$key.'.email'" label="Email" :id="'email-'.$key">
                                    <x-form.select :name="'prefs['.$key.'][email]'" :id="'email-'.$key" :value="$c['email']" :options="collect($meta['modes'])->mapWithKeys(fn ($m) => [$m => $modeWords[$m]])->all()" required />
                                </x-form.field>
                            @endif
                        </div>
                    @endif

                    @if ($key === 'knowledge')
                        <x-form.switch name="knowledge_in_app" id="knowledge-in-app" label="Record these on my notifications page" :checked="$knowledgeInApp" hint="Turn this off and nothing new is recorded about your questions and answers. Notices about bookings, seminars you paid for and your companies are never held back by it." />
                    @endif
                </div>
            </section>
        @endforeach

        <p class="ix-hint">The daily summary arrives once a day (about 07:30 South African time), only when there is something new, and every summary has a link to stop it.</p>
        <button type="submit" class="ix-btn ix-btn--primary">Save my settings</button>
    </form>
</x-app-layout>

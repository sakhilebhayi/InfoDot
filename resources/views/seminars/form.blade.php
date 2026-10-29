<x-app-layout>
    @php($editing = $seminar !== null)
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $editing ? 'Edit your seminar' : 'Host a seminar' }}</h1>
            <p class="ix-lede">Seminars are free. @if (config('seminars.video.enabled')) You can run it in an InfoDot room or bring your own meeting link; @else InfoDot does not run video here yet: you bring your own meeting room (any video call or webinar link); @endif only people who registered get in, from {{ config('seminars.join_opens_minutes_before', 15) }} minutes before the start.</p>
        </div>
    </div>

    <form class="ix-form" method="POST" action="{{ $editing ? route('seminars.update', $seminar) : route('seminars.store') }}">
        @csrf
        @if ($editing)@method('PUT')@endif

        @foreach ([['title', 'Title', 'text', 140, 'What will people learn?'], ['summary', 'One-line summary', 'text', 280, 'Shown in the list.']] as [$name, $label, $type, $max, $hint])
            <div class="ix-field">
                <label class="ix-label" for="{{ $name }}">{{ $label }}</label>
                <input class="ix-input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($name, $seminar?->$name) }}" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
                <p class="ix-hint">{{ $hint }}</p>
                @error($name)<p class="ix-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="ix-field">
            <label class="ix-label" for="description">Details (optional)</label>
            <textarea class="ix-textarea" id="description" name="description" rows="6" maxlength="5000">{{ old('description', $seminar?->description) }}</textarea>
            @error('description')<p class="ix-error">{{ $message }}</p>@enderror
        </div>

        <div class="ix-field">
            <label class="ix-label" for="topic_id">Topic (optional)</label>
            <select class="ix-input ix-select" id="topic_id" name="topic_id">
                <option value="">No topic</option>
                @foreach ($topics as $t)
                    <option value="{{ $t->id }}" @selected((int) old('topic_id', $seminar?->topic_id) === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="ix-form__row">
            <div class="ix-field">
                <label class="ix-label" for="starts_at">Starts ({{ config('app.timezone') }})</label>
                <input class="ix-input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $seminar?->starts_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" required>
                @error('starts_at')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label class="ix-label" for="duration_minutes">Length in minutes</label>
                <input class="ix-input" id="duration_minutes" name="duration_minutes" type="number" min="{{ config('seminars.min_duration_minutes') }}" max="{{ config('seminars.max_duration_minutes') }}" value="{{ old('duration_minutes', $seminar?->duration_minutes ?? 60) }}" required>
                @error('duration_minutes')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label class="ix-label" for="capacity">Places (optional)</label>
                <input class="ix-input" id="capacity" name="capacity" type="number" min="1" max="{{ config('seminars.max_capacity') }}" value="{{ old('capacity', $seminar?->capacity) }}">
                <p class="ix-hint">Leave empty for no limit. Extra people join a waiting list.</p>
                @error('capacity')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($paidOn)
            <div class="ix-field">
                <label class="ix-label" for="price">Ticket price in rand (leave empty for a free seminar)</label>
                @if ($hasPayout || $seminar?->isPaid())
                    <input class="ix-input" id="price" name="price" type="text" inputmode="decimal" placeholder="200.00" value="{{ old('price', $seminar?->price_cents ? number_format($seminar->price_cents / 100, 2, '.', '') : '') }}">
                    <p class="ix-hint">You set the price. InfoDot keeps {{ $percent }}% of each ticket and Paystack's processing fee comes out of your share, so on a R200.00 ticket you receive R{{ number_format(200 * (100 - $percent) / 100, 2) }} before Paystack's fee. Tickets are refunded in full if you cancel. Once someone buys a ticket the price cannot change.</p>
                @else
                    <p class="ix-hint">To charge for a seminar, first <a href="{{ route('seminars.payouts') }}" style="text-decoration: underline;">set up where your earnings are paid</a>.</p>
                @endif
                @error('price')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
        @endif

        @php($videoOn = config('seminars.video.enabled'))
        @php($p2pOn = config('seminars.p2p.enabled'))
        @php($qualities = config('seminars.p2p.qualities'))
        <div class="ix-field" x-data='seminarDelivery(@json(old("delivery", $seminar?->delivery ?? "link")), @json((int) old("p2p_uplink_kbps", $seminar?->p2p_uplink_kbps ?? 0)), @json(old("p2p_quality", $seminar?->p2p_quality ?? "low")), @json(route("seminars.p2p.speedtest")), @json(route("seminars.p2p.plan")))'>
            @if ($videoOn || $p2pOn)
                <span class="ix-label" id="delivery-h">How will you run it?</span>
                <div role="radiogroup" aria-labelledby="delivery-h" style="display: grid; gap: 6px; margin-bottom: 8px;">
                    @if ($videoOn)<label><input type="radio" name="delivery" value="room" x-model="delivery"> InfoDot room (video in the browser, for large audiences)</label>@endif
                    @if ($p2pOn)<label><input type="radio" name="delivery" value="p2p" x-model="delivery"> Small room (video in the browser, no server: the number of people depends on your internet connection)</label>@endif
                    <label><input type="radio" name="delivery" value="link" x-model="delivery"> My own meeting link</label>
                </div>
                @error('delivery')<p class="ix-error">{{ $message }}</p>@enderror
            @endif

            <div x-show="delivery === 'link'" @if ($videoOn || $p2pOn) x-cloak @endif>
                <label class="ix-label" for="join_url">Your meeting link (https)</label>
                <input class="ix-input" id="join_url" name="join_url" type="url" maxlength="500" value="{{ old('join_url', $seminar?->join_url) }}" placeholder="https://">
                <p class="ix-hint">Only people who registered are given this link, and only shortly before the start.</p>
                @error('join_url')<p class="ix-error">{{ $message }}</p>@enderror
            </div>

            @if ($videoOn)
                <p class="ix-hint" x-show="delivery === 'room'" x-cloak>People who registered enter the room from this page from {{ config('seminars.join_opens_minutes_before', 15) }} minutes before the start. You share your camera, microphone and screen; they watch and can chat. Nothing is recorded.</p>
            @endif

            @if ($p2pOn)
                <div x-show="delivery === 'p2p'" x-cloak style="border: 1px solid var(--ix-rule); border-radius: var(--ix-radius); padding: 14px;">
                    <p class="ix-hint" style="margin-top: 0;">A small room sends your video straight from your computer to the people watching, so how many can join depends on <b>your</b> upload speed and computer. Test your connection and we will tell you the number before you publish. Nothing is recorded.</p>
                    <input type="hidden" name="p2p_uplink_kbps" :value="kbps">
                    <button type="button" class="ix-btn" @click="test()" :disabled="testing"><span x-text="testing ? 'Testing…' : (kbps ? 'Test again' : 'Test my connection')"></span></button>
                    <p class="ix-hint" x-show="message" x-text="message" role="status"></p>
                    <template x-if="kbps">
                        <div>
                            <p style="margin: 12px 0 4px;"><b>Your upload speed: <span x-text="(kbps / 1000).toFixed(1)"></span> Mbps.</b> Choose how you want to send:</p>
                            <div role="radiogroup" style="display: grid; gap: 6px;">
                                @foreach ($qualities as $key => $q)
                                    <label :style="plans['{{ $key }}'] && !plans['{{ $key }}'].usable ? 'opacity: .5' : ''">
                                        <input type="radio" name="p2p_quality" value="{{ $key }}" x-model="quality" :disabled="plans['{{ $key }}'] && !plans['{{ $key }}'].usable">
                                        {{ $q['label'] }}:
                                        <span x-text="plans['{{ $key }}'] ? (plans['{{ $key }}'].usable ? 'up to ' + plans['{{ $key }}'].capacity + ' people' : 'too slow for this connection') : '…'"></span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="ix-hint" x-show="plans[quality] && plans[quality].usable"><b>This seminar can have up to <span x-text="plans[quality].capacity"></span> people</b> (your computer feeds <span x-text="plans[quality].direct"></span> directly; the rest receive it through other viewers). That figure is a cautious estimate: phones and strict networks can lower it, and anyone beyond it joins the waiting list. Audio only holds the most people.</p>
                        </div>
                    </template>
                    @error('p2p_uplink_kbps')<p class="ix-error">{{ $message }}</p>@enderror
                    @error('p2p_quality')<p class="ix-error">{{ $message }}</p>@enderror
                </div>
            @endif
        </div>

        <div class="ix-actions">
            <button class="ix-btn ix-btn--primary" type="submit" name="publish" value="1">{{ $seminar?->isPublished() ? 'Save' : 'Publish' }}</button>
            @if (! $seminar?->isPublished())<button class="ix-btn" type="submit">Save as draft</button>@endif
            <a class="ix-btn ix-btn--ghost" href="{{ $editing ? route('seminars.show', $seminar) : route('seminars') }}">Cancel</a>
        </div>
    </form>
    <script>
            function seminarDelivery(delivery, kbps, quality, speedUrl, planUrl) {
                return {
                    delivery, kbps, quality, plans: {}, testing: false, message: '',
                    async init() { if (this.kbps) await this.loadPlans(); },
                    async loadPlans() {
                        const r = await fetch(planUrl + '?kbps=' + this.kbps, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        this.plans = r.ok ? await r.json() : {};
                        if (this.plans[this.quality] && !this.plans[this.quality].usable) this.quality = Object.keys(this.plans).find((k) => this.plans[k].usable) || this.quality;
                    },
                    async test() {
                        this.testing = true; this.message = 'Sending a test file to measure your upload speed…';
                        const token = document.querySelector('meta[name="csrf-token"]')?.content;
                        const blob = new Blob([new Uint8Array(1_000_000)]);
                        const speeds = [];
                        try {
                            for (let i = 0; i < 2; i++) {
                                const t0 = performance.now();
                                const res = await fetch(speedUrl, { method: 'POST', body: blob, headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/octet-stream' }, credentials: 'same-origin' });
                                if (!res.ok) throw new Error('bad');
                                speeds.push((blob.size * 8) / ((performance.now() - t0) / 1000) / 1000);
                            }
                            this.kbps = Math.max(50, Math.round(Math.min(...speeds)));
                            this.message = 'Done.';
                            await this.loadPlans();
                        } catch (e) {
                            this.message = 'The test failed. Check your connection and try again.';
                        } finally { this.testing = false; }
                    },
                };
            }
    </script>
</x-app-layout>

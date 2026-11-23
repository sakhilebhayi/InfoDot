<x-public-layout title="Contact" band="CONTACT" crumb="Contact" reveal description="How to contact InfoDot: account help, your personal information, something wrong on the site, security reports and general questions.">
    @php
        $img = fn (string $n) => \Illuminate\Support\Facades\Vite::asset('resources/img/pages/'.$n.'.jpg');
        $routes = [
            ['plate' => 'Account', 'tone' => 'var(--ix-knowledge)', 'title' => 'Help with your account', 'text' => 'You cannot sign in, or you think someone else has access to your account. Tell us straight away.', 'go' => 'support@infodot.co.za', 'href' => 'mailto:support@infodot.co.za'],
            ['plate' => 'Privacy', 'tone' => 'var(--ix-people)', 'title' => 'Your personal information', 'text' => 'Ask what InfoDot holds about you, correct it, or ask for it to be removed.', 'go' => 'privacy@infodot.co.za', 'href' => 'mailto:privacy@infodot.co.za'],
            ['plate' => 'Report', 'tone' => 'var(--ix-files)', 'title' => 'Something wrong on InfoDot', 'text' => 'A question or answer that is wrong or breaks the rules has a report link. This page explains the rest.', 'go' => 'How to complain', 'href' => route('complains')],
            ['plate' => 'Security', 'tone' => 'var(--ix-analytics)', 'title' => 'Found a security problem?', 'text' => 'Read how to report a vulnerability, what to expect from us and what we ask of you.', 'go' => 'Vulnerability disclosure', 'href' => route('security')],
            ['plate' => 'Answers', 'tone' => 'var(--ix-documents)', 'title' => 'A question about InfoDot', 'text' => 'What it is, what it costs to join, who can see what you write and how answers are checked.', 'go' => 'Questions people ask', 'href' => route('faqs')],
        ];
    @endphp

    <section class="pg-hero" aria-labelledby="contact-h">
        <div class="pg-hero__copy">
            <span class="pg-plate" data-pg-plate>Write to us</span>
            <h1 id="contact-h" class="pg-hero__title" data-pg-in>Tell us what you need.</h1>
            <p class="pg-hero__lede" data-pg-in>Questions, ideas and concerns about InfoDot are welcome. Choose the lane that fits and your message reaches the right place first.</p>
        </div>
        <figure class="pg-photo" data-pg-photo>
            <img src="{{ $img('contact-1600') }}" srcset="{{ $img('contact-800') }} 800w, {{ $img('contact-1600') }} 1600w" sizes="(max-width: 900px) 100vw, 40vw" alt="A woman in a mustard cardigan working on a laptop at a dark desk by a window" width="1600" height="1067" decoding="async">
            <figcaption>Photograph by <a href="https://unsplash.com/photos/woman-using-laptop-in-workspace-HA-0i0E7sq4" rel="noopener">Daniel Thomas</a> on Unsplash</figcaption>
        </figure>
    </section>

    <section aria-labelledby="lanes">
        <h2 id="lanes" class="sr-only">Who to write to</h2>
        <ul class="pg-routes">
            @foreach ($routes as $r)
                <li class="pg-route" style="--pg-tone: {{ $r['tone'] }}" data-pg-reveal>
                    <span class="pg-plate" data-pg-stamp>{{ $r['plate'] }}</span>
                    <h3>{{ $r['title'] }}</h3>
                    <p>{{ $r['text'] }}</p>
                    <a class="pg-route__go" href="{{ $r['href'] }}">{{ $r['go'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>

    @if (filled(config('mail.contact_to')))
        <section class="pg-split" aria-labelledby="send-h">
            <div data-pg-reveal>
                <h2 id="send-h">Prefer to write here?</h2>
                <p>Send a message from this page and we reply to the email address you give. We use what you send only to reply to you.</p>
            </div>
            <div data-pg-reveal>
                @if ($message = Session::get('success'))
                    <p class="pg-note" role="status">{{ $message }}</p>
                @endif
                <form class="pg-form" action="{{ route('send-contact') }}" method="POST">
                    @csrf
                    <div class="ix-field">
                        <label class="ix-label" for="full-name">Full name</label>
                        <input type="text" class="ix-input" id="full-name" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name')<p class="ix-error" id="name-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="email">Email address</label>
                        <input type="email" class="ix-input" id="email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')<p class="ix-error" id="email-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ix-field">
                        <label class="ix-label" for="message">Message</label>
                        <textarea class="ix-textarea" id="message" name="message" rows="6" maxlength="4000" required @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                        @error('message')<p class="ix-error" id="message-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ix-sr" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <button type="submit" class="ix-btn ix-btn--primary">Send message</button>
                </form>
            </div>
        </section>
    @endif

    <p class="pg-note" data-pg-reveal><strong>Please do not send passwords or other secrets.</strong> No one at InfoDot will ever ask you for your password.</p>
</x-public-layout>

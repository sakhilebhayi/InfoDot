<x-public-layout title="Contact" band="INFO" crumb="Contact" description="How to contact InfoDot: questions, ideas, concerns and requests about your personal information.">
    <div class="ix-prose">
        <h1 class="ix-h1">Contact us</h1>
        <p class="ix-lede">Questions, ideas or concerns about InfoDot are welcome.</p>

        <h2>Write to us</h2>
        <ul>
            <li><strong>About your personal information:</strong> <a href="mailto:privacy@infodot.co.za">privacy@infodot.co.za</a></li>
        </ul>
        <p>If a question or answer on InfoDot is wrong or breaks the rules, use the report link on it, or see <a href="{{ route('complains') }}">how to complain</a>. Please do not send passwords or other secrets.</p>

        @if (filled(config('mail.contact_to')))
            <h2>Or send a message here</h2>
            @if ($message = Session::get('success'))
                <p class="ix-pts__muted" role="status">{{ $message }}</p>
            @endif
            <form class="ix-form" action="{{ route('send-contact') }}" method="POST">
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
                    <textarea class="ix-input" id="message" name="message" rows="6" maxlength="4000" required @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                    @error('message')<p class="ix-error" id="message-error">{{ $message }}</p>@enderror
                </div>
                <div class="ix-sr" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <button type="submit" class="ix-btn ix-btn--primary">Send message</button>
                <p class="ix-hint">We use what you send only to reply to you.</p>
            </form>
        @endif
    </div>
</x-public-layout>

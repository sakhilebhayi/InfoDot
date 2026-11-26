{{-- R-67: the InfoDot side of its own way in. The homepage's promise, then what the account opens, in three lines that are
     all true today. No photograph and no motion: the page is for getting through, and the panel only says why it is worth it. --}}
@props(['kind' => 'register'])
<aside class="ix-entry__panel" aria-labelledby="entry-panel-title">
    @if ($kind === 'login')
        <h2 id="entry-panel-title" class="ix-entry__panel-title">A better, more connected way to build a business.</h2>
        <dl class="ix-entry__panel-list">
            <div><dt>Your knowledge</dt><dd>Your company's questions and solutions, and everything other businesses have shared.</dd></div>
            <div><dt>Your business</dt><dd>Your business page, your team and the messages and appointments in your inbox.</dd></div>
            <div><dt>Your tools</dt><dd>Every Dot tool your business uses, opened with this one sign-in.</dd></div>
        </dl>
    @else
        <h2 id="entry-panel-title" class="ix-entry__panel-title">Your first step to a better-equipped business.</h2>
        <dl class="ix-entry__panel-list">
            <div><dt>Stop figuring it out alone</dt><dd>Ask questions, see what other businesses learned and follow researched solutions.</dd></div>
            <div><dt>Be easy to find</dt><dd>Give your business a page in the directory, and receive messages and appointment requests.</dd></div>
            <div><dt>Bring your team and tools together</dt><dd>Add the people who work with you, and open the Dot tools your business uses with one account.</dd></div>
        </dl>
        <p class="ix-entry__panel-foot">Creating an account is free. You set up your company on the next step.</p>
    @endif
</aside>

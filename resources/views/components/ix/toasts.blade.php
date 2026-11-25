{{--
    The pop-up area (R-64 phase 6): short-lived news in the top right corner. New notifications arrive here while a page is open (the page asks for them
    every 45 seconds while it is visible); any script can add one with window.dispatchEvent(new CustomEvent('ix-toast', { detail: { text, href?, label?, tone? } })).
    A pop-up never replaces the record: every notification is still in the bell's page. Pop-ups are announced politely, stop their timer while hovered or
    focused, close with Escape, and are never the only way to learn something. `flash` is what a request left behind with App\Support\Toast::flash():
    shown once the page that followed it has loaded, so success is never announced before the server has confirmed it.
--}}
@props(['last' => 0, 'url' => null, 'flash' => null])
<div class="ix-toasts" x-data="ixToasts({ url: @js($url), last: {{ (int) $last }}, flash: @js($flash) })" x-on:ix-toast.window="push($event.detail)"
     role="region" aria-label="Notifications" aria-live="polite" aria-relevant="additions">
    <template x-for="t in items" x-bind:key="t.key">
        <div class="ix-toast" x-bind:class="'ix-toast--' + t.tone" x-on:mouseenter="hold(t)" x-on:mouseleave="release(t)" x-on:focusin="hold(t)" x-on:focusout="release(t)" x-on:keydown.escape="dismiss(t)">
            <span class="ix-toast__label" x-text="t.label"></span>
            <p class="ix-toast__text" x-text="t.text"></p>
            <a class="ix-toast__link" x-show="t.href" x-bind:href="t.href" x-text="t.action"></a>
            <button type="button" class="ix-toast__close" x-on:click="dismiss(t)" aria-label="Dismiss this notification"><span aria-hidden="true">&times;</span></button>
        </div>
    </template>
</div>

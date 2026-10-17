@props(['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')])

<div x-data="{{ json_encode(['show' => true, 'style' => $style, 'message' => $message]) }}"
            style="display: none; background: var(--ix-panel); color: var(--ix-text); border-bottom: 1px solid var(--ix-rule-strong);"
            class="relative z-[60] lg:ml-[var(--ix-rail-w)]"
            role="status"
            x-show="show && message"
            x-init="
                document.addEventListener('banner-message', event => {
                    style = event.detail.style;
                    message = event.detail.message;
                    show = true;
                });
            ">
    <div class="flex items-center justify-between gap-3 px-4 py-2 sm:px-6">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <span class="material-symbols-rounded"
                  aria-hidden="true"
                  :style="style == 'danger' ? 'color: var(--ix-danger)' : (style == 'success' ? 'color: var(--ix-ok)' : 'color: var(--ix-text-2)')"
                  x-text="style == 'danger' ? 'error' : (style == 'success' ? 'check_circle' : 'info')"></span>

            <p class="truncate" style="margin: 0; font-weight: 500;" x-text="message"></p>
        </div>

        <button type="button" class="ix-btn ix-btn--ghost ix-btn--icon ix-btn--sm" aria-label="Dismiss" x-on:click="show = false">
            <span class="material-symbols-rounded" aria-hidden="true">close</span>
        </button>
    </div>
</div>

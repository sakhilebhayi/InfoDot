@props(['id' => null, 'maxWidth' => null])

<x-jet-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="flex items-start gap-3" style="padding: 20px 20px 16px;">
        <span class="material-symbols-rounded" aria-hidden="true" style="color: var(--ix-danger); margin-top: 1px;">warning</span>

        <div class="min-w-0">
            <h2 style="margin: 0; font-family: var(--ix-font-cond); font-weight: 700; font-size: 18px; line-height: 1.3;">
                {{ $title }}
            </h2>

            <div class="mt-2" style="color: var(--ix-text-2);">
                {{ $content }}
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2" style="padding: 12px 16px; border-top: 1px solid var(--ix-rule); background: var(--ix-sunken);">
        {{ $footer }}
    </div>
</x-jet-modal>

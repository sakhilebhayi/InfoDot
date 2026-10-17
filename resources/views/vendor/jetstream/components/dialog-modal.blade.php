@props(['id' => null, 'maxWidth' => null])

<x-jet-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div style="padding: 20px 20px 16px;">
        <h2 style="margin: 0; font-family: var(--ix-font-display); font-weight: 700; font-size: 18px; line-height: 1.3;">
            {{ $title }}
        </h2>

        <div class="mt-3" style="color: var(--ix-text-2);">
            {{ $content }}
        </div>
    </div>

    <div class="flex items-center justify-end gap-2" style="padding: 12px 16px; border-top: 1px solid var(--ix-rule); background: var(--ix-sunken);">
        {{ $footer }}
    </div>
</x-jet-modal>

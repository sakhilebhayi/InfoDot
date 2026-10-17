<div {{ $attributes->merge(['class' => 'space-y-4 md:grid md:grid-cols-3 md:gap-8 md:space-y-0', 'style' => 'padding: 24px 0; border-top: 1px solid var(--ix-rule);']) }}>
    <x-jet-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-jet-section-title>

    <div class="md:col-span-2">
        <div class="ix-panel">
            <div class="ix-panel__body">
                {{ $content }}
            </div>
        </div>
    </div>
</div>

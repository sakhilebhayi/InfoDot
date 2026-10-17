@props(['submit'])

<div {{ $attributes->merge(['class' => 'space-y-4 md:grid md:grid-cols-3 md:gap-8 md:space-y-0', 'style' => 'padding: 24px 0; border-top: 1px solid var(--ix-rule);']) }}>
    <x-jet-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-jet-section-title>

    <div class="md:col-span-2">
        <form wire:submit.prevent="{{ $submit }}">
            <div class="ix-panel">
                <div class="ix-panel__body">
                    <div class="grid grid-cols-6 gap-x-5 gap-y-5">
                        {{ $form }}
                    </div>
                </div>

                @if (isset($actions))
                    <div class="flex items-center justify-end gap-3" style="padding: 12px 16px; border-top: 1px solid var(--ix-rule); background: var(--ix-sunken); border-radius: 0 0 2px 2px;">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

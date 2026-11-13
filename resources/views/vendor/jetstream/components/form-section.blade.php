@props(['submit'])

<div {{ $attributes->merge(['class' => 'ix-settings__block space-y-4 md:grid md:grid-cols-3 md:gap-8 md:space-y-0']) }}>
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
                    <div class="ix-settings__actions">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

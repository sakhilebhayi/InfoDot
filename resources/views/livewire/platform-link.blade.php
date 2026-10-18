<div @class(['contents' => $variant === 'tab', 'h-full' => $variant === 'door'])>
    @if ($live)
        @if ($variant === 'tab')
            <button type="button" class="ix-tab" data-area="{{ $area }}" wire:click="open" wire:loading.attr="disabled">
                {{ $label }}<span class="material-symbols-rounded ix-out" aria-hidden="true">arrow_outward</span>
            </button>
        @elseif ($variant === 'door')
            <button type="button" class="ix-door" data-area="{{ $area }}" wire:click="open" wire:loading.attr="disabled">
                <span class="ix-door__top">
                    <span class="ix-door__label">{{ $label }}</span>
                    <span class="ix-logotile ix-logotile--sq"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/platforms/'.($platform === 'docs' ? 'docs' : 'files').'.png') }}" alt="Dot.{{ $platform === 'docs' ? 'docs' : 'Files' }}" width="72" height="72" loading="lazy"></span>
                </span>
                <span class="ix-door__go">Open signed in <span class="material-symbols-rounded" aria-hidden="true">arrow_outward</span></span>
            </button>
        @else
            <button type="button" class="ix-pop__item" wire:click="open" wire:loading.attr="disabled">
                <span class="material-symbols-rounded" aria-hidden="true">{{ $icon }}</span>{{ $label }}
                <span class="material-symbols-rounded" aria-hidden="true" style="margin-left:auto;font-size:16px">arrow_outward</span>
            </button>
        @endif
    @endif
</div>

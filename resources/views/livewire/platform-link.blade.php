<div>
    @if ($live)
        <button type="button" class="ix-nav__link" wire:click="open" wire:loading.attr="disabled">
            <span class="material-symbols-rounded" aria-hidden="true">{{ $icon }}</span>
            {{ $label }}
            <span class="material-symbols-rounded ix-nav__out" aria-hidden="true">arrow_outward</span>
        </button>
    @endif
</div>

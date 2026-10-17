@props(['on'])

<div x-data="{ shown: false, timeout: null }"
    x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000);  })"
    x-show.transition.out.opacity.duration.1500ms="shown"
    x-transition:leave.opacity.duration.1500ms
    role="status"
    style="display: none; color: var(--ix-text-2);"
    {{ $attributes->merge(['class' => 'text-sm leading-6']) }}>
    {{ $slot->isEmpty() ? 'Saved.' : $slot }}
</div>

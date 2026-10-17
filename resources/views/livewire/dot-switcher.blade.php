@if($inline)
    {{-- Inline sidebar widget: always expanded --}}
    <div style="font-family:'Inter',sans-serif;">
        <p style="font-size:11px;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:#b6c4ff;margin-bottom:0.75rem;">Dot Ecosystem</p>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;">
            @foreach($this->platforms as $key => $platform)
                <button wire:click="switchTo('{{ $key }}')"
                        style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;padding:0.6rem 0.25rem;border-radius:0.75rem;background:rgba(49,57,77,0.4);border:1px solid rgba(67,70,86,0.2);cursor:pointer;transition:all 0.2s;width:100%;"
                        onmouseover="this.style.background='rgba(41,98,255,0.15)';this.style.borderColor='rgba(41,98,255,0.35)'"
                        onmouseout="this.style.background='rgba(49,57,77,0.4)';this.style.borderColor='rgba(67,70,86,0.2)'"
                        title="{{ $platform['name'] }}">
                    <span class="material-symbols-rounded" style="font-size:20px;color:#b6c4ff;">{{ $platform['icon'] }}</span>
                    <span style="font-size:0.55rem;font-weight:700;color:#b7c8e1;letter-spacing:0.03em;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ Str::after($platform['name'], '.') }}</span>
                </button>
            @endforeach
        </div>
    </div>
@else
    {{-- Nav dropdown: toggled by button --}}
    <div x-data="{ open: @entangle('open') }" class="relative">

        <button @click="open = !open"
                style="display:flex;align-items:center;gap:0.5rem;padding:0.45rem 0.85rem;border-radius:9999px;background:rgba(41,98,255,0.12);border:1px solid rgba(41,98,255,0.25);font-family:'Manrope',sans-serif;font-size:0.75rem;font-weight:700;color:#b6c4ff;cursor:pointer;transition:background 0.2s;"
                onmouseover="this.style.background='rgba(41,98,255,0.22)'"
                onmouseout="this.style.background='rgba(41,98,255,0.12)'"
                title="Switch Dot platform">
            <span class="material-symbols-rounded" style="font-size:18px;">apps</span>
            <span>Dot Apps</span>
            <span class="material-symbols-rounded" style="font-size:14px;transition:transform 0.2s;" :style="open ? 'transform:rotate(180deg)' : ''">expand_more</span>
        </button>

        <div x-show="open"
             x-cloak
             @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             style="position:absolute;top:calc(100% + 0.5rem);right:0;width:320px;background:#131b2e;border:1px solid rgba(67,70,86,0.3);border-radius:1rem;box-shadow:0 20px 60px rgba(6,14,32,0.6);z-index:100;padding:1rem;display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;">

            @foreach($this->platforms as $key => $platform)
                <button wire:click="switchTo('{{ $key }}')"
                        style="display:flex;flex-direction:column;align-items:center;gap:0.35rem;padding:0.65rem 0.5rem;border-radius:0.75rem;background:rgba(49,57,77,0.4);border:1px solid rgba(67,70,86,0.2);cursor:pointer;transition:all 0.2s;"
                        onmouseover="this.style.background='rgba(41,98,255,0.15)';this.style.borderColor='rgba(41,98,255,0.35)'"
                        onmouseout="this.style.background='rgba(49,57,77,0.4)';this.style.borderColor='rgba(67,70,86,0.2)'"
                        title="{{ $platform['name'] }}">
                    <span class="material-symbols-rounded" style="font-size:22px;color:#b6c4ff;">{{ $platform['icon'] }}</span>
                    <span style="font-size:0.6rem;font-weight:700;color:#b7c8e1;letter-spacing:0.04em;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $platform['name'] }}</span>
                </button>
            @endforeach

        </div>
    </div>
@endif

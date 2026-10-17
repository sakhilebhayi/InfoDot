<div style="font-family:'Inter',sans-serif;">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#b6c4ff]">Dot Ecosystem</p>
            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#dae2fd]" style="font-family:'Manrope',sans-serif;">Your Platforms</h2>
        </div>
        <span class="rounded-full border border-[#2962ff]/30 bg-[#2962ff]/10 px-4 py-1.5 text-xs font-bold text-[#b6c4ff]">
            18 platforms
        </span>
    </div>

    {{-- Groups --}}
    <div class="space-y-7">
        @foreach($this->groups as $groupName => $platforms)
            @if(count($platforms))
                <div>
                    {{-- Group label --}}
                    <div class="mb-3 flex items-center gap-3">
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#8d90a2]">{{ $groupName }}</span>
                        <div class="h-px flex-1 bg-[#434656]/25"></div>
                    </div>

                    {{-- Platform cards --}}
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                        @foreach($platforms as $key => $platform)
                            <button
                                wire:click="launch('{{ $key }}')"
                                wire:loading.attr="disabled"
                                class="group relative flex flex-col items-center gap-2 rounded-2xl border p-4 text-center transition-all duration-200
                                    {{ $launching === $key
                                        ? 'border-[#2962ff]/60 bg-[#2962ff]/15 scale-95'
                                        : 'border-[#434656]/25 bg-[rgba(49,57,77,0.45)] hover:border-[#2962ff]/40 hover:bg-[#2962ff]/10 hover:scale-[1.03]' }}"
                                style="backdrop-filter:blur(12px);"
                                title="{{ $platform['name'] }}"
                            >
                                {{-- Loading spinner --}}
                                @if($launching === $key)
                                    <div wire:loading wire:target="launch('{{ $key }}')"
                                         class="absolute inset-0 flex items-center justify-center rounded-2xl bg-[#131b2e]/60">
                                        <svg class="h-5 w-5 animate-spin text-[#2962ff]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                @endif

                                {{-- Icon --}}
                                <span class="material-symbols-rounded transition-colors duration-200
                                    {{ $launching === $key ? 'text-[#2962ff]' : 'text-[#b6c4ff] group-hover:text-[#2962ff]' }}"
                                    style="font-size:26px;">
                                    {{ $platform['icon'] }}
                                </span>

                                {{-- Name --}}
                                <span class="w-full truncate text-[0.6rem] font-bold leading-4 tracking-wide transition-colors duration-200
                                    {{ $launching === $key ? 'text-[#b6c4ff]' : 'text-[#b7c8e1] group-hover:text-[#dae2fd]' }}">
                                    {{ $platform['name'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Footer note --}}
    <p class="mt-6 text-[11px] text-[#434656] text-center">
        Single sign-on &mdash; click any platform to launch with your current session
    </p>
</div>

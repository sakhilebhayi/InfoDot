{{-- Token colours via arbitrary values so they beat the forms plugin's base reset; checked fill is currentColor (the signal). --}}
<input type="checkbox" {!! $attributes->merge(['class' => 'h-4 w-4 cursor-pointer border border-[color:var(--ix-rule-strong)] bg-[color:var(--ix-sunken)] text-[color:var(--ix-signal)] focus:ring-0 focus:ring-offset-0']) !!} style="border-radius: var(--ix-radius);">

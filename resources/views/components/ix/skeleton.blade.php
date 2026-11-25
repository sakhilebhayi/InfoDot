{{--
    A placeholder for content that is on its way (R-64 phase 10): the shape of the thing, pulsing quietly, labelled for screen readers as "Loading". Use it only
    where something really is loading, and take it away when the content arrives (or fails: then say so). `lines` is how many text lines, `title` adds a heading
    bar, `block` adds a card-sized block.
--}}
@props(['lines' => 3, 'title' => false, 'block' => false, 'label' => 'Loading'])
<div {{ $attributes->class(['ix-skel-group']) }} role="status" aria-busy="true">
    <span class="ix-sr">{{ $label }}</span>
    @if ($title)<span class="ix-skel ix-skel--title" aria-hidden="true"></span>@endif
    @if ($block)<span class="ix-skel ix-skel--block" aria-hidden="true"></span>@endif
    @for ($i = 0; $i < (int) $lines; $i++)
        <span class="ix-skel" style="width: {{ 100 - ($i % 3) * 12 }}%" aria-hidden="true"></span>
    @endfor
</div>

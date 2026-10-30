{{--
    R-15 attachment display. Include with $attachments (a collection, already in display order) and optionally
    $compact (answers/comments: smaller grid, no section labels). Media keeps its place: images as a captioned grid
    with a full-screen lightbox, PDFs as cards that preview inline on demand, videos in the native player.
--}}
@php
    $attachments = collect($attachments ?? []);
    $compact = $compact ?? false;
    $images = $attachments->where('kind', \App\Models\Attachment::IMAGE)->values();
    $pdfs = $attachments->where('kind', \App\Models\Attachment::PDF)->values();
    $videos = $attachments->where('kind', \App\Models\Attachment::VIDEO)->values();
@endphp
@if ($attachments->isNotEmpty())
    <div class="ix-attview {{ $compact ? 'ix-attview--compact' : '' }}">
        @if ($images->isNotEmpty())
            <div x-data="attachmentLightbox(@js($images->map(fn ($a) => ['url' => route('attachments.show', $a), 'caption' => $a->caption ?: $a->name])->all()))">
                <ul class="ix-attview__grid" role="list">
                    @foreach ($images as $i => $image)
                        <li class="ix-attview__cell">
                            <button type="button" class="ix-attview__imgbtn" @click="open({{ $i }})" aria-label="View full screen: {{ $image->caption ?: $image->name }}">
                                <img src="{{ $image->thumb_path ? route('attachments.thumb', $image) : route('attachments.show', $image) }}"
                                     alt="{{ $image->caption ?: $image->name }}" loading="lazy">
                            </button>
                            @if ($image->caption)<p class="ix-attview__cap">{{ $image->caption }}</p>@endif
                        </li>
                    @endforeach
                </ul>
                <dialog class="ix-lightbox" x-ref="dialog" @click.self="close()"
                        @keydown.arrow-right.prevent="step(1)" @keydown.arrow-left.prevent="step(-1)" aria-label="Image viewer">
                    <button type="button" class="ix-lightbox__btn ix-lightbox__close" @click="close()" aria-label="Close">
                        <span class="material-symbols-rounded" aria-hidden="true">close</span>
                    </button>
                    @if ($images->count() > 1)
                        <button type="button" class="ix-lightbox__btn ix-lightbox__prev" @click="step(-1)" aria-label="Previous image">
                            <span class="material-symbols-rounded" aria-hidden="true">chevron_left</span>
                        </button>
                        <button type="button" class="ix-lightbox__btn ix-lightbox__next" @click="step(1)" aria-label="Next image">
                            <span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>
                        </button>
                    @endif
                    <figure class="ix-lightbox__stage">
                        <img :src="images[at]?.url" :alt="images[at]?.caption ?? ''">
                        <figcaption x-text="images[at]?.caption"></figcaption>
                    </figure>
                </dialog>
            </div>
        @endif

        @foreach ($videos as $video)
            <figure class="ix-attview__video">
                {{-- No autoplay, no preloaded bytes beyond the header; the controller answers Range requests so seeking works. --}}
                <video controls preload="metadata" playsinline src="{{ route('attachments.show', $video) }}"></video>
                <figcaption>{{ $video->caption ?: $video->name }} <small>({{ strtoupper(pathinfo($video->name, PATHINFO_EXTENSION)) }}, {{ $video->sizeLabel() }})</small></figcaption>
            </figure>
        @endforeach

        @foreach ($pdfs as $pdf)
            <div class="ix-attview__file" x-data="{ open: false }">
                <div class="ix-attview__filerow">
                    <span class="ix-attach__thumb" aria-hidden="true"><span class="material-symbols-rounded">picture_as_pdf</span></span>
                    <span class="ix-attach__body">
                        <span class="ix-attach__name">{{ $pdf->name }} <small>{{ $pdf->sizeLabel() }}</small></span>
                        @if ($pdf->caption)<span class="ix-attview__cap" style="margin:0">{{ $pdf->caption }}</span>@endif
                    </span>
                    <span class="ix-attview__fileactions">
                        <button type="button" class="ix-btn ix-btn--sm ix-btn--ghost" @click="open = !open" :aria-expanded="open" x-text="open ? 'Hide preview' : 'Preview'">Preview</button>
                        <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('attachments.show', [$pdf, 'download' => 1]) }}">Download</a>
                    </span>
                </div>
                <template x-if="open">
                    <iframe class="ix-attview__pdf" src="{{ route('attachments.show', $pdf) }}" title="Preview of {{ $pdf->name }}" loading="lazy"></iframe>
                </template>
            </div>
        @endforeach
    </div>
@endif

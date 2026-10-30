{{-- Feed indicators (R-15): what a post carries besides text. Include with $item after withAttachmentCounts(). --}}
@php
    $images = (int) ($item->image_attachments_count ?? 0) + (int) ($item->step_image_count ?? 0);
    $pdfs = (int) ($item->pdf_attachments_count ?? 0) + (int) ($item->step_pdf_count ?? 0);
    $videos = (int) ($item->video_attachments_count ?? 0) + (int) ($item->step_video_count ?? 0);
@endphp
@if ($images > 0)
    <span class="ix-attmeta" title="{{ $images }} {{ Str::plural('image', $images) }}"><span class="material-symbols-rounded" aria-hidden="true">image</span>{{ $images }}<span class="ix-sr">{{ Str::plural('image', $images) }}</span></span>
@endif
@if ($pdfs > 0)
    <span class="ix-attmeta" title="{{ $pdfs }} {{ Str::plural('document', $pdfs) }}"><span class="material-symbols-rounded" aria-hidden="true">picture_as_pdf</span>{{ $pdfs }}<span class="ix-sr">{{ Str::plural('document', $pdfs) }}</span></span>
@endif
@if ($videos > 0)
    <span class="ix-attmeta" title="{{ $videos }} {{ Str::plural('video', $videos) }}"><span class="material-symbols-rounded" aria-hidden="true">movie</span>{{ $videos }}<span class="ix-sr">{{ Str::plural('video', $videos) }}</span></span>
@endif

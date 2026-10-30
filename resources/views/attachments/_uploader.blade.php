{{--
    R-15 composer uploader. Include with:
      $idPrefix   unique id prefix on the page (required)
      $nameExpr   JS expression for the field prefix of a plain form, e.g. "'attachments'" or "'step_attachments[' + i + ']'".
                  Omit for a Livewire form and pass $payloadModel instead (the wire:model of a hidden JSON field).
      $itemsExpr  optional JS expression for an external items array (a per-step array that survives re-renders)
      $label      optional label text
    Plain forms get attachments[<n>][uuid|caption] inputs; Livewire forms get one hidden JSON payload field.
--}}
@php
    $nameExpr = $nameExpr ?? "'attachments'";
    $itemsExpr = $itemsExpr ?? 'null';
    $payloadModel = $payloadModel ?? null;

    // After a failed validation, put the person's still-unbound uploads back into the widget.
    $preload = [];
    $oldRows = collect((array) ($old ?? []))->filter(fn ($row) => is_array($row) && Illuminate\Support\Str::isUuid($row['uuid'] ?? ''));
    if ($oldRows->isNotEmpty()) {
        $captions = $oldRows->pluck('caption', 'uuid');
        $preload = App\Models\Attachment::whereIn('uuid', $oldRows->pluck('uuid'))
            ->where('user_id', auth()->id())->whereNull('attachable_id')->get()
            ->map(fn ($a) => [
                'uuid' => $a->uuid, 'kind' => $a->kind, 'name' => $a->name, 'size' => $a->size,
                'thumb' => $a->thumb_path ? route('attachments.thumb', $a) : null,
                'caption' => (string) ($captions[$a->uuid] ?? ''),
            ])->values()->all();
    }
@endphp
<div class="ix-field" x-id="['{{ $idPrefix }}-att']"
     x-data="attachmentUploader({ url: @js(route('attachments.store')), max: {{ (int) config('attachments.max_per_item') }}, items: {!! $itemsExpr !!}, preload: @js($preload) })"
     @isset($clearEvent) x-on:{{ $clearEvent }}.window="clearAll()" @endisset>
    <span class="ix-label" :id="$id('{{ $idPrefix }}-att') + '-label'">{{ $label ?? 'Attachments' }} <span style="color:var(--ix-text-3);font-weight:400">(optional)</span></span>

    <div class="ix-attach" :class="{ 'is-over': over }"
         @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop($event)">
        <input type="file" class="ix-sr" multiple x-ref="input" :accept="accept" :id="$id('{{ $idPrefix }}-att') + '-input'"
               aria-label="Add attachment files" @change="add($event.target.files); $event.target.value = ''">
        <button type="button" class="ix-btn ix-btn--sm" @click="pick()">
            <span class="material-symbols-rounded" aria-hidden="true" style="font-size:16px">attach_file</span>Add files
        </button>
        <p class="ix-hint" style="margin:0">Images, PDFs or short videos (MP4/WebM) — drop them here, up to <span x-text="max"></span>.</p>
    </div>

    <p class="ix-error" x-show="note" x-text="note" x-cloak role="alert"></p>

    <ul class="ix-attach__list" x-show="items.length" x-cloak>
        <template x-for="it in items" :key="it.key">
            <li class="ix-attach__item" :class="{ 'is-error': it.error }">
                <span class="ix-attach__thumb" aria-hidden="true">
                    <img x-show="it.thumb || it.preview" :src="it.thumb ?? it.preview" alt="">
                    <span class="material-symbols-rounded" x-show="!(it.thumb || it.preview)" x-text="icon(it)"></span>
                </span>
                <span class="ix-attach__body">
                    <span class="ix-attach__name"><span x-text="it.name"></span> <small x-text="fmt(it.size)"></small></span>
                    <span class="ix-attach__bar" x-show="it.xhr" aria-hidden="true"><span :style="'width:' + it.progress + '%'"></span></span>
                    <span class="ix-error" x-show="it.error" x-text="it.error" role="alert"></span>
                    <input class="ix-input ix-attach__caption" type="text" maxlength="500" placeholder="Caption: what this shows"
                           aria-label="Attachment caption" x-show="it.uuid" x-model="it.caption" @input="sync()" :aria-label="'Caption for ' + it.name">
                </span>
                <span class="ix-attach__tools">
                    <button type="button" class="ix-attach__tool" x-show="items.length > 1" @click="move(it, -1)" :aria-label="'Move ' + it.name + ' earlier'">
                        <span class="material-symbols-rounded" aria-hidden="true">arrow_upward</span>
                    </button>
                    <button type="button" class="ix-attach__tool" x-show="items.length > 1" @click="move(it, 1)" :aria-label="'Move ' + it.name + ' later'">
                        <span class="material-symbols-rounded" aria-hidden="true">arrow_downward</span>
                    </button>
                    <button type="button" class="ix-attach__tool" @click="remove(it)" :aria-label="(it.xhr ? 'Cancel ' : 'Remove ') + it.name">
                        <span class="material-symbols-rounded" aria-hidden="true" x-text="it.xhr ? 'cancel' : 'delete'"></span>
                    </button>
                </span>
            </li>
        </template>
    </ul>

    @if ($payloadModel)
        <input type="hidden" x-ref="payload" wire:model="{{ $payloadModel }}">
    @else
        <template x-for="(it, idx) in ready" :key="'f' + it.key">
            <span>
                <input type="hidden" :name="{!! $nameExpr !!} + '[' + idx + '][uuid]'" :value="it.uuid">
                <input type="hidden" :name="{!! $nameExpr !!} + '[' + idx + '][caption]'" :value="it.caption">
            </span>
        </template>
    @endif
</div>

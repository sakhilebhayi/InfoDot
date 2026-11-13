{{-- One service or product: $s is the row being edited, or null for a new one; $sfx keeps ids unique. --}}
<div class="ix-form__row"><label class="ix-label" for="o_name_{{ $sfx }}">Name</label><input class="ix-input" id="o_name_{{ $sfx }}" name="name" minlength="2" maxlength="120" required value="{{ $s ? $s->name : old('name') }}" placeholder="For example: Monthly bookkeeping"></div>
<div class="ix-form__row">
    <label class="ix-label" for="o_kind_{{ $sfx }}">It is a</label>
    <select class="ix-input" id="o_kind_{{ $sfx }}" name="kind">
        @foreach (\App\Models\BusinessService::KINDS as $value => $label)
            <option value="{{ $value }}" @selected(($s ? $s->kind : old('kind', 'service')) === $value)>{{ mb_strtolower($label) }}</option>
        @endforeach
    </select>
</div>
<div class="ix-form__row"><label class="ix-label" for="o_desc_{{ $sfx }}">In a sentence or two <span class="ix-hint">optional</span></label><textarea class="ix-textarea" id="o_desc_{{ $sfx }}" name="description" rows="2" maxlength="600">{{ $s ? $s->description : old('description') }}</textarea></div>
<div class="ix-form__row"><label class="ix-label" for="o_price_{{ $sfx }}">Price <span class="ix-hint">optional; shows exactly as written</span></label><input class="ix-input" id="o_price_{{ $sfx }}" name="price_note" maxlength="120" value="{{ $s ? $s->price_note : old('price_note') }}" placeholder="For example: From R450 a month"></div>
<div class="ix-form__row"><label class="ix-label" for="o_img_{{ $sfx }}">Picture <span class="ix-hint">optional{{ $s ? '; replaces the current one' : '' }}</span></label><input class="ix-input" type="file" id="o_img_{{ $sfx }}" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></div>

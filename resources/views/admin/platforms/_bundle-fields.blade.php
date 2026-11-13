{{-- Name, description, contents, price and switch for a bundle. Pass $bundle (or null), $choices, $p. --}}
@php $inside = collect(old('platforms', $bundle?->items->pluck('platform_key')->all() ?? [])); @endphp
<div class="ix-field">
    <label class="ix-label" for="{{ $p }}-name">Bundle name</label>
    <input id="{{ $p }}-name" name="name" type="text" maxlength="120" class="ix-input" value="{{ old('name', $bundle?->name) }}" required>
</div>
<div class="ix-field">
    <label class="ix-label" for="{{ $p }}-description">What it is for (optional)</label>
    <textarea id="{{ $p }}-description" name="description" class="ix-textarea" rows="2" maxlength="1000">{{ old('description', $bundle?->description) }}</textarea>
</div>
<fieldset class="ix-vfieldset">
    <legend class="ix-label">Platforms in the bundle (at least two)</legend>
    <div class="ix-adm__picks">
        @foreach ($choices as $choice)
            <label><input type="checkbox" name="platforms[]" value="{{ $choice->key }}" @checked($inside->contains($choice->key))><span>{{ $choice->name }}</span></label>
        @endforeach
    </div>
</fieldset>
@include('admin.platforms._price-fields', ['of' => $bundle, 'p' => $p])
@if ($bundle)
    <fieldset class="ix-vchecks">
        <label>
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $bundle->active))>
            <span>Switched on. A bundle is on sale only when it is switched on and its price is approved.</span>
        </label>
    </fieldset>
@endif

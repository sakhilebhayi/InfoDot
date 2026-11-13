{{-- The reason every change carries. Pass a unique $id; $label and $rows are optional. --}}
<div class="ix-field">
    <label class="ix-label" for="{{ $id }}">{{ $label ?? 'Why you are making this change (10 to 255 characters, kept in the audit trail)' }}</label>
    <textarea id="{{ $id }}" name="reason" class="ix-textarea" rows="{{ $rows ?? 2 }}" minlength="10" maxlength="255" required>{{ ($sticky ?? true) ? old('reason') : '' }}</textarea>
</div>

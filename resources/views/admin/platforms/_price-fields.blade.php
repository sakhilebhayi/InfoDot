{{-- Amount, currency, interval. Pass $of (a plan, a bundle or null) and a unique $p for ids. Nothing is pre-filled for a new one: no price is ever suggested. --}}
<div class="ix-adm__price">
    <div class="ix-field">
        <label class="ix-label" for="{{ $p }}-amount">Amount, in minor units</label>
        <input id="{{ $p }}-amount" name="amount_minor" type="number" inputmode="numeric" min="0" step="1" class="ix-input" value="{{ old('amount_minor', $of?->amount_minor) }}" aria-describedby="{{ $p }}-amount-hint">
    </div>
    <div class="ix-field">
        <label class="ix-label" for="{{ $p }}-currency">Currency code</label>
        <input id="{{ $p }}-currency" name="currency" type="text" maxlength="3" class="ix-input ix-adm__code" value="{{ old('currency', $of?->currency) }}" autocomplete="off" autocapitalize="characters" spellcheck="false">
    </div>
    <div class="ix-field">
        <label class="ix-label" for="{{ $p }}-interval">Repeats</label>
        <select id="{{ $p }}-interval" name="interval" class="ix-input ix-select">
            <option value="" @selected(old('interval', $of?->interval) === null)>Does not repeat</option>
            <option value="month" @selected(old('interval', $of?->interval) === 'month')>Every month</option>
            <option value="year" @selected(old('interval', $of?->interval) === 'year')>Every year</option>
        </select>
    </div>
</div>
<p class="ix-hint" id="{{ $p }}-amount-hint">Minor units are cents: 19900 means 199.00. Leave the amount and the currency empty while the price is undecided. No price is filled in for you.</p>

<x-app-layout>
    @php($flash = session('seminar_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Get paid for seminars</h1>
            <p class="ix-lede">You set the price of each seminar. InfoDot keeps {{ $percent }}% of every ticket and Paystack's processing fee comes out of your share. Paystack pays the rest to the bank account you give here.</p>
        </div>
    </div>
    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif

    @if ($account)
        <p><span class="ix-chip ix-chip--done">Payout account saved</span> Ending {{ $account->account_last4 }}. Enter new details below to replace it.</p>
    @endif

    <form class="ix-form" method="POST" action="{{ route('seminars.payouts.store') }}" autocomplete="off">
        @csrf
        <div class="ix-field">
            <label class="ix-label" for="business_name">Name on the account</label>
            <input class="ix-input" id="business_name" name="business_name" type="text" maxlength="100" value="{{ old('business_name') }}" required>
            @error('business_name')<p class="ix-error">{{ $message }}</p>@enderror
        </div>
        <div class="ix-field">
            <label class="ix-label" for="settlement_bank">Bank</label>
            <select class="ix-input ix-select" id="settlement_bank" name="settlement_bank" required>
                <option value="">Choose your bank</option>
                @foreach ($banks as $b)
                    <option value="{{ $b['code'] }}" @selected(old('settlement_bank') === $b['code'])>{{ $b['name'] }}</option>
                @endforeach
            </select>
            @if (empty($banks))<p class="ix-hint">The bank list could not be loaded just now. Try again in a minute.</p>@endif
            @error('settlement_bank')<p class="ix-error">{{ $message }}</p>@enderror
        </div>
        <div class="ix-field">
            <label class="ix-label" for="account_number">Account number</label>
            <input class="ix-input" id="account_number" name="account_number" type="text" inputmode="numeric" pattern="\d{6,20}" maxlength="20" autocomplete="off" required>
            <p class="ix-hint">This goes straight to our payment partner. InfoDot keeps only the last four digits.</p>
            @error('account_number')<p class="ix-error">{{ $message }}</p>@enderror
        </div>
        <div class="ix-actions">
            <button class="ix-btn ix-btn--primary" type="submit">Save payout account</button>
            <a class="ix-btn ix-btn--ghost" href="{{ route('seminars') }}">Back to seminars</a>
        </div>
    </form>

    <section aria-labelledby="seller-h" style="margin-top: 28px;">
        <h2 class="ix-label" id="seller-h">Who is selling the ticket</h2>
        <p class="ix-hint">You are the seller; InfoDot collects the payment and keeps {{ $percent }}% as its commission. This is what the attendee's receipt says. Add a VAT number only if you are registered for VAT.</p>
        <form class="ix-form" method="POST" action="{{ route('seminars.payouts.seller') }}">
            @csrf
            <div class="ix-field">
                <label class="ix-label" for="trading_name">Name to show as the seller</label>
                <input class="ix-input" id="trading_name" name="trading_name" type="text" maxlength="100" value="{{ old('trading_name', $seller?->trading_name ?? auth()->user()->name) }}" required>
                @error('trading_name')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label class="ix-label" for="vat_number">VAT number (optional)</label>
                <input class="ix-input" id="vat_number" name="vat_number" type="text" inputmode="numeric" maxlength="10" value="{{ old('vat_number', $seller?->vat_number) }}">
                @error('vat_number')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <div class="ix-field">
                <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                    <input type="checkbox" name="authorises_receipts" value="1" style="margin-top:3px" @checked(old('authorises_receipts', $seller?->authorises_receipts))>
                    <span class="ix-hint" style="margin:0">I authorise InfoDot to issue receipts to my attendees in my name.</span>
                </label>
            </div>
            <button class="ix-btn" type="submit">Save seller details</button>
            <a class="ix-btn ix-btn--ghost" href="{{ route('seminars.invoices') }}">My commission invoices</a>
        </form>
    </section>
</x-app-layout>

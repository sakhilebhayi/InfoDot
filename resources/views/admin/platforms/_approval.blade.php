{{-- The explicit price approval step for a plan or bundle. Pass $of, $action (route for approve/withdraw with a decision placeholder closure), $kind ('plan'|'bundle'), $onSale (bool), $approval (the latest price_approved event or null). --}}
<section class="ix-panel" aria-labelledby="approve-h">
    <div class="ix-panel__head"><h2 id="approve-h">Price approval</h2>
        @if ($onSale)<span class="ix-chip ix-chip--done">On sale</span>@elseif ($of->approved_at)<span class="ix-chip">Approved, switched off</span>@else<span class="ix-chip ix-chip--open">Not approved</span>@endif
    </div>
    <div class="ix-panel__body ix-vactions">
        @if ($of->approved_at)
            <dl class="ix-biz__facts">
                <div><dt>Approved price</dt><dd><strong>{{ \App\Support\Money::format($of->amount_minor, $of->currency) }}</strong>@if ($of->interval) a {{ $of->interval }}@endif</dd></div>
                <div><dt>Approved</dt><dd>{{ $of->approved_at->format('j M Y, H:i') }}@if ($approval?->actor) by {{ $approval->actor->name }}@endif</dd></div>
                <div><dt>On sale</dt><dd>{{ $onSale ? 'Yes' : 'No: the '.$kind.' is switched off. Switch it on in the form to sell it.' }}</dd></div>
            </dl>
            <details>
                <summary class="ix-btn ix-btn--danger">Withdraw the approval…</summary>
                <form method="post" action="{{ $withdraw }}" class="ix-form ix-vgap">
                    @csrf
                    <p class="ix-hint">The {{ $kind }} goes off sale at once. Companies that already hold it keep what they have.</p>
                    @include('admin.platforms._reason', ['id' => 'withdraw-reason', 'sticky' => false])
                    <div><button type="submit" class="ix-btn ix-btn--danger">Withdraw the approval</button></div>
                </form>
            </details>
        @elseif ($of->amount_minor === null || $of->currency === null)
            <p class="ix-hint">There is no price to approve. Enter an amount and a currency in the form and save it first.</p>
        @else
            <form method="post" action="{{ $approve }}" class="ix-form">
                @csrf
                <dl class="ix-biz__facts">
                    <div><dt>Price to approve</dt><dd><strong>{{ \App\Support\Money::format($of->amount_minor, $of->currency) }}</strong>@if ($of->interval) a {{ $of->interval }}@endif ({{ $of->amount_minor }} minor units)</dd></div>
                </dl>
                @include('admin.platforms._reason', ['id' => 'approve-reason', 'label' => 'Who decided this price and when (10 to 255 characters, kept in the audit trail)', 'sticky' => false])
                <fieldset class="ix-vchecks">
                    <label><input type="checkbox" name="confirm" value="1" required><span>I approve this exact price. Companies can be charged it once the {{ $kind }} is switched on.</span></label>
                </fieldset>
                <div><button type="submit" class="ix-btn ix-btn--primary">Approve the price</button></div>
            </form>
        @endif
    </div>
</section>

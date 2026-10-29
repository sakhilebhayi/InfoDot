<?php

namespace App\Http\Controllers;

use App\Models\SeminarPayoutAccount;
use App\Services\Seminars\BillingMarketplace;
use App\Services\Seminars\MarketplaceUnavailable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a host says where their ticket earnings are paid. The account number goes straight to Dot.Billing (and on to Paystack) and is
 * not stored, flashed or logged here: only Billing's id for the account and the last four digits are kept.
 */
class SeminarPayoutsController extends Controller
{
    public function __construct(private readonly BillingMarketplace $billing) {}

    private function switchedOn(): void
    {
        abort_unless($this->billing->configured(), 404);
    }

    public function index(Request $request): View
    {
        $this->switchedOn();

        try {
            $banks = $this->billing->banks();
        } catch (MarketplaceUnavailable) {
            $banks = [];
        }

        return view('seminars.payouts', [
            'account' => SeminarPayoutAccount::where('user_id', $request->user()->id)->first(), 'banks' => $banks,
            'seller' => \App\Models\SeminarSellerProfile::where('user_id', $request->user()->id)->first(),
            'percent' => (int) config('seminars.paid.platform_percent', 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->switchedOn();
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'settlement_bank' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'account_number' => ['required', 'regex:/^\d{6,20}$/'],
        ]);

        try {
            $result = $this->billing->createPayoutAccount('u'.$request->user()->id, $data['business_name'], $data['settlement_bank'], $data['account_number']);
        } catch (MarketplaceUnavailable $e) {
            return back()->withInput($request->except('account_number'))->withErrors(['account_number' => $e->getMessage()]);
        }

        SeminarPayoutAccount::updateOrCreate(['user_id' => $request->user()->id], [
            'billing_account_id' => $result['id'], 'bank_code' => $result['bank_code'], 'account_last4' => $result['last4'], 'status' => $result['status'] === 'active' ? 'active' : 'disabled',
        ]);

        return redirect()->route('seminars.payouts')->with('seminar_notice', 'Your payout account is saved (ending '.$result['last4'].').');
    }

    /** Who the seller is on receipts, and whether InfoDot may issue them for the host. */
    public function seller(Request $request): RedirectResponse
    {
        $this->switchedOn();
        $data = $request->validate([
            'trading_name' => ['required', 'string', 'max:100'],
            'vat_number' => ['nullable', 'regex:/^4\d{9}$/'],
            'authorises_receipts' => ['nullable', 'boolean'],
        ], ['vat_number.regex' => 'A South African VAT number is 10 digits and starts with 4. Leave it empty if you are not registered for VAT.']);

        \App\Models\SeminarSellerProfile::updateOrCreate(['user_id' => $request->user()->id], [
            'trading_name' => $data['trading_name'], 'vat_number' => $data['vat_number'] ?: null, 'authorises_receipts' => $request->boolean('authorises_receipts'),
        ]);

        return redirect()->route('seminars.payouts')->with('seminar_notice', 'Your seller details are saved.');
    }
}

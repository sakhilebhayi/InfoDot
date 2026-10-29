<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarRegistration;
use App\Models\Topic;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** Free seminars. Hosts manage their own; everyone signed in can find, register for, join and rate. Drafts are the host's alone. */
class SeminarsController extends Controller
{
    public function __construct(private readonly SeminarService $seminars) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $topicId = $request->integer('topic') ?: null;
        $upcoming = Seminar::upcomingPublished()->when($topicId, fn ($c) => $c->where('topic_id', $topicId))->values();
        $registered = SeminarRegistration::where('user_id', $user->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->pluck('status', 'seminar_id');

        return view('seminars.index', [
            'upcoming' => $upcoming, 'registered' => $registered, 'topics' => Topic::active()->ordered()->get(), 'topicId' => $topicId,
            'mine' => Seminar::where('host_id', $user->id)->where('status', '!=', Seminar::CANCELLED)->orderBy('starts_at')->get()->reject->hasEnded()->values(),
            'hosts' => User::whereIn('id', $upcoming->pluck('host_id'))->get()->keyBy('id'),
        ]);
    }

    public function create(): View
    {
        return view('seminars.form', ['seminar' => null, 'topics' => Topic::active()->ordered()->get()] + $this->payoutState(auth()->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(SeminarService::rules());
        $seminar = $this->seminars->save($request->user(), null, $data, $request->boolean('publish'));

        return redirect()->route('seminars.show', $seminar)->with('seminar_notice', $seminar->isPublished() ? 'Your seminar is published.' : 'Saved as a draft. Only you can see it.');
    }

    public function show(Request $request, Seminar $seminar): View
    {
        $user = $request->user();
        $isHost = $seminar->host_id === $user->id;
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->first();
        abort_if($seminar->status === Seminar::DRAFT && ! $isHost, 404);
        abort_if($seminar->status === Seminar::CANCELLED && ! $isHost && ! ($reg && $reg->status !== SeminarRegistration::CANCELLED), 404);

        return view('seminars.show', [
            'seminar' => $seminar->load('topic'), 'host' => $seminar->host, 'isHost' => $isHost,
            'reg' => $reg && $reg->status !== SeminarRegistration::CANCELLED ? $reg : null,
            'registeredCount' => SeminarRegistration::where('seminar_id', $seminar->id)->where('status', SeminarRegistration::REGISTERED)->count(),
            'stats' => $this->seminars->hostStats($seminar->host),
            'linkReports' => $isHost ? $this->seminars->linkReports($seminar) : 0,
            'revenue' => $isHost && $seminar->isPaid() ? $this->seminars->hostRevenue($seminar) : null,
            'people' => $isHost ? SeminarRegistration::with('user')->where('seminar_id', $seminar->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->orderBy('id')->get() : collect(),
            'myFeedback' => SeminarFeedback::where('seminar_id', $seminar->id)->where('user_id', $user->id)->first(),
            'feedback' => $isHost ? SeminarFeedback::where('seminar_id', $seminar->id)->latest()->get() : collect(),
        ]);
    }

    public function edit(Request $request, Seminar $seminar): View
    {
        $this->hostOnly($request, $seminar);

        return view('seminars.form', ['seminar' => $seminar, 'topics' => Topic::active()->ordered()->get()] + $this->payoutState($request->user()));
    }

    public function update(Request $request, Seminar $seminar): RedirectResponse
    {
        $this->hostOnly($request, $seminar);
        $data = $request->validate(SeminarService::rules());
        $this->seminars->save($request->user(), $seminar, $data, $request->boolean('publish'));

        return redirect()->route('seminars.show', $seminar)->with('seminar_notice', 'Saved.');
    }

    public function cancel(Request $request, Seminar $seminar): RedirectResponse
    {
        $this->hostOnly($request, $seminar);
        $this->seminars->cancel($seminar);

        return redirect()->route('seminars.show', $seminar)->with('seminar_notice', 'The seminar is cancelled and the people who registered were told.');
    }

    public function register(Request $request, Seminar $seminar): RedirectResponse
    {
        abort_unless($seminar->isPublished(), 404);

        if ($seminar->isPaid()) {
            // Never a registration yet: that happens only when Billing reports the payment verified.
            return redirect()->away($this->seminars->startCheckout($seminar, $request->user()));
        }

        $status = $this->seminars->register($seminar, $request->user());

        return back()->with('seminar_notice', $status === SeminarRegistration::WAITLIST ? 'The seminar is full. You are on the waiting list and will be moved up if a place opens.' : 'You are registered.');
    }

    public function unregister(Request $request, Seminar $seminar): RedirectResponse
    {
        $this->seminars->unregister($seminar, $request->user());

        return back()->with('seminar_notice', 'Your registration is cancelled.');
    }

    /** Hands a registered person on to the host's own meeting link, only inside the window; this is also what records attendance. */
    public function join(Request $request, Seminar $seminar): RedirectResponse
    {
        if ($seminar->isRoom()) {
            return redirect()->route('seminars.room', $seminar);
        }

        if ($seminar->isP2p()) {
            return redirect()->route('seminars.p2p', $seminar);
        }

        $url = $this->seminars->joinUrl($seminar, $request->user());

        return $url !== null
            ? redirect()->away($url)
            : redirect()->route('seminars.show', $seminar)->with('seminar_notice', 'The link opens '.config('seminars.join_opens_minutes_before', 15).' minutes before the start, for people who registered.');
    }

    public function attendance(Request $request, Seminar $seminar, int $person): RedirectResponse
    {
        $this->hostOnly($request, $seminar);
        $this->seminars->setAttended($seminar, User::findOrFail($person), $request->boolean('attended'));

        return back();
    }

    public function feedback(Request $request, Seminar $seminar): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $this->seminars->rate($seminar, $request->user(), (int) $data['rating'], $data['comment'] ?? null);

        return back()->with('seminar_notice', 'Thank you. The host sees your comment; only the rating counts toward the host’s average.');
    }

    /** The host refunds part (or all the rest) of one ticket. Confirmed by Billing before the ticket changes. */
    public function refund(Request $request, Seminar $seminar, int $person): RedirectResponse
    {
        $this->hostOnly($request, $seminar);
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/']]);
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $person)->firstOrFail();
        $this->seminars->hostRefund($seminar, $reg, (int) round(((float) $data['amount']) * 100));

        return back()->with('seminar_notice', 'The refund was sent to our payment partner. The ticket updates when it is confirmed.');
    }

    /** The buyer's receipt: who the seller is, what was paid and refunded. Only the person who bought the ticket. */
    public function receipt(Request $request, Seminar $seminar): View
    {
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $request->user()->id)->whereNotNull('paid_at')->firstOrFail();
        $profile = \App\Models\SeminarSellerProfile::where('user_id', $seminar->host_id)->first();
        $taxInvoice = config('seminars.paid.issue_tax_invoices') && $profile?->vat_number && $profile->authorises_receipts;
        $rate = (int) config('seminars.paid.vat_rate_percent', 15);

        return view('seminars.receipt', [
            'seminar' => $seminar, 'reg' => $reg, 'buyer' => $request->user(), 'seller' => $profile?->trading_name ?: $seminar->host->name, 'vatNumber' => $taxInvoice ? $profile->vat_number : null,
            'taxInvoice' => $taxInvoice, 'vatIncluded' => $taxInvoice ? (int) floor(((int) $reg->amount_cents) * $rate / (100 + $rate) + 0.5) : null, 'rate' => $rate,
        ]);
    }

    /** The "test this link" button on the form. */
    public function linkCheck(Request $request): \Illuminate\Http\JsonResponse
    {
        $url = (string) $request->query('url', '');
        abort_if(strlen($url) > 500, 422);

        return response()->json(app(\App\Services\Seminars\MeetingLinkCheck::class)->check($url), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** The short InfoDot link. It always opens: it goes to the seminar's page (signing in first when needed). */
    public function short(string $code): RedirectResponse
    {
        $seminar = Seminar::where('public_code', strtolower($code))->firstOrFail();
        abort_if($seminar->status === Seminar::DRAFT, 404);

        return redirect()->route('seminars.show', $seminar);
    }

    /** A signed-in person reports a seminar to the moderators. */
    public function report(Request $request, Seminar $seminar): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'in:'.implode(',', array_keys(\App\Models\SeminarReport::REASONS))], 'detail' => ['nullable', 'string', 'max:1000']]);
        $this->seminars->report($seminar, $request->user(), $data['reason'], $data['detail'] ?? null);

        return back()->with('seminar_notice', 'Thank you. A moderator will look at it. The host is not told who reported it.');
    }

    /** "The link does not work": tells the host. */
    public function reportLink(Request $request, Seminar $seminar): RedirectResponse
    {
        $this->seminars->reportLink($seminar, $request->user());

        return back()->with('seminar_notice', 'Thank you. We told the host the link did not open.');
    }

    /** Whether this host can sell tickets: paid seminars on, and a payout account saved. */
    private function payoutState($user): array
    {
        return [
            'paidOn' => (bool) config('seminars.paid.enabled'),
            'hasPayout' => \App\Models\SeminarPayoutAccount::where('user_id', $user->id)->where('status', 'active')->exists(),
            'percent' => (int) config('seminars.paid.platform_percent', 10),
        ];
    }

    private function hostOnly(Request $request, Seminar $seminar): void
    {
        abort_unless($seminar->host_id === $request->user()->id, 403);
    }
}

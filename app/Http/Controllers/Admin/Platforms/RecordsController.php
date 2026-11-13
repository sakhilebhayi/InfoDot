<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Controller;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformPaymentConfirmation;
use App\Models\Team;
use App\Services\Platforms\EntitlementOutbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The admin centre's three read-mostly records (R-50 section 9): the outbox that tells each platform about an
 * entitlement change (with retry), the payments Dot.Finance has confirmed (nothing else is ever counted as revenue),
 * and the audit trail of the catalogue.
 */
class RecordsController extends Controller
{
    private const PER_PAGE = 40;

    public function outbox(Request $request): View
    {
        $data = $request->validate(['state' => ['nullable', Rule::in(['waiting', 'parked', 'delivered'])]]);
        $state = $data['state'] ?? 'waiting';

        $rows = DB::table('entitlement_outbox')
            ->when($state === 'waiting', fn ($q) => $q->whereNull('sent_at')->whereNull('failed_at'))
            ->when($state === 'parked', fn ($q) => $q->whereNull('sent_at')->whereNotNull('failed_at'))
            ->when($state === 'delivered', fn ($q) => $q->whereNotNull('sent_at'))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.platforms.outbox', [
            'state' => $state,
            'rows' => $rows,
            'companies' => Team::whereIn('uuid', collect($rows->items())->pluck('team_uuid')->unique())->get()->keyBy('uuid'),
            'counts' => [
                'waiting' => DB::table('entitlement_outbox')->whereNull('sent_at')->whereNull('failed_at')->count(),
                'parked' => DB::table('entitlement_outbox')->whereNull('sent_at')->whereNotNull('failed_at')->count(),
                'delivered' => DB::table('entitlement_outbox')->whereNotNull('sent_at')->count(),
            ],
        ]);
    }

    public function retry(Request $request, EntitlementOutbox $outbox, int $row): RedirectResponse
    {
        $event = DB::table('entitlement_outbox')->where('id', $row)->first();
        abort_if($event === null, 404);

        $back = redirect()->route('admin.platforms.outbox', ['state' => $request->input('state') === 'parked' ? 'parked' : 'waiting']);

        if (! $outbox->retry($row)) {
            return $back->with('status', 'That event has already been delivered. Nothing was changed.');
        }

        PlatformCatalogueEvent::create([
            'actor_id' => $request->user()->id, 'subject_type' => 'outbox', 'subject_key' => (string) $event->event_id, 'type' => 'delivery_retried',
            'before' => ['attempts' => (int) $event->attempts, 'parked' => $event->failed_at !== null], 'after' => ['attempts' => 0, 'parked' => false],
            'reason' => 'Retried from the admin centre.', 'created_at' => now(),
        ]);

        return $back->with('status', 'The event is queued again and will be sent within a minute.');
    }

    public function payments(): View
    {
        $counted = PlatformPaymentConfirmation::query()->where('outcome', PlatformPaymentConfirmation::APPLIED)->whereNotNull('currency');

        return view('admin.platforms.payments', [
            'confirmations' => PlatformPaymentConfirmation::with(['team', 'plan'])->orderByDesc('id')->paginate(self::PER_PAGE),
            'received' => (clone $counted)->whereIn('event', [PlatformPaymentConfirmation::PAID, PlatformPaymentConfirmation::RENEWED])->selectRaw('currency, sum(amount_minor) as total, count(*) as n')->groupBy('currency')->orderBy('currency')->get(),
            'refunded' => (clone $counted)->where('event', PlatformPaymentConfirmation::REFUNDED)->selectRaw('currency, sum(amount_minor) as total')->groupBy('currency')->pluck('total', 'currency'),
        ]);
    }

    public function audit(Request $request): View
    {
        $data = $request->validate(['about' => ['nullable', Rule::in(['platform', 'plan', 'bundle', 'administrator', 'outbox'])]]);

        return view('admin.platforms.audit', [
            'about' => $data['about'] ?? null,
            'events' => PlatformCatalogueEvent::with('actor')->when($data['about'] ?? null, fn ($q, $about) => $q->where('subject_type', $about))
                ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }
}

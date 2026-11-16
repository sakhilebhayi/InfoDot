<?php

namespace App\Http\Controllers;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\FinanceCheckout;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\LicenceBatches;
use App\Services\Licensing\LicenceOverview;
use App\Services\Licensing\LicencePricing;
use App\Services\Licensing\LicenceRefused;
use App\Services\Licensing\SeatLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Team Licences screens (R-53 phase 3): the upgrade page, the owner's actions behind a page that says who loses access and when, and
 * the pages Dot.Finance sends the owner back to. Everything is scoped to one company the person belongs to; a company they do not belong to
 * is a 404, and one they belong to without the right is a 403. Nothing here activates a licence: that only follows Finance's signed
 * `paid` confirmation (LicenceBatchEvents), so the return pages only ever read the ledger.
 */
class TeamLicencesController extends Controller
{
    private const ACTIONS = ['release', 'assign', 'reassign', 'reduce', 'cancel', 'close'];

    public function __construct(
        private LicenceOverview $overview,
        private LicencePricing $pricing,
        private LicenceBatches $batches,
        private LicenceAssignments $assignments,
        private SeatLedger $ledger,
        private FinanceCheckout $finance,
    ) {}

    /** The upgrade page: why a licence is needed, what it includes, the cost, and a quantity and billing choice. */
    public function buy(Request $request, Team $team): View
    {
        $this->authorise($request, $team, false);

        $quantity = max(1, min(1000, (int) $request->query('quantity', 1)));
        $interval = in_array($request->query('interval'), LicenceBatch::INTERVALS, true) ? $request->query('interval') : LicenceBatch::MONTH;
        $quotes = [];
        $refusal = null;

        foreach (LicenceBatch::INTERVALS as $i) {
            try {
                $quotes[$i] = $this->pricing->quote($team, $quantity, $i);
            } catch (LicenceRefused $e) {
                $quotes[$i] = null;
                $refusal ??= $e->getMessage();
            }
        }

        $saving = $quotes['month'] && $quotes['year'] ? max(0, $quotes['month']['total_minor'] * 12 - $quotes['year']['total_minor']) : null;

        return view('licences.buy', [
            'team' => $team, 'quantity' => $quantity, 'interval' => $interval, 'quotes' => $quotes, 'saving' => $saving, 'refusal' => $refusal,
            'seats' => $this->ledger->summary($team), 'price' => $this->ledger->price(), 'isOwner' => Gate::check('changeLicence', $team),
            'connected' => $this->finance->configured(), 'bands' => $this->pricing->bandText(),
        ]);
    }

    /** Pay: the price is worked out on the server from configuration; only the quantity and the interval come from the browser. */
    public function checkout(Request $request, Team $team): RedirectResponse
    {
        $this->authorise($request, $team, true);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000'], 'interval' => ['required', 'in:month,year']]);

        try {
            $started = $this->batches->startPurchase($team, $request->user(), (int) $data['quantity'], $data['interval']);
        } catch (LicenceRefused $e) {
            return redirect()->route('licences.buy', ['team' => $team->uuid, 'quantity' => $data['quantity'], 'interval' => $data['interval']])->with('licence_error', $e->getMessage());
        }

        Log::info('licence.checkout_started', ['batch' => $started['batch']->uuid]);

        return redirect()->away($started['url']);
    }

    /** Where Dot.Finance sends the owner back. Reads the ledger; the address can say success or failure but neither changes anything. */
    public function returned(Request $request, ?string $result = null): View
    {
        $batch = $this->batchFromReturn($request);
        abort_if($batch === null || $batch->team === null, 404);
        $team = $batch->team;
        $this->authorise($request, $team, false);

        $key = 'licence_return_seen.'.$batch->uuid;
        $seen = Session::get($key) ?? tap(now()->timestamp, fn ($t) => Session::put($key, $t));
        $waiting = $batch->status === LicenceBatch::PENDING;
        $waitedOut = now()->timestamp - (int) $seen > max(1, (int) config('licensing.return_wait_minutes', 10)) * 60;

        return view('licences.returned', [
            'team' => $team, 'batch' => $batch, 'result' => $result === 'failed' ? 'failed' : 'success', 'waiting' => $waiting,
            'refresh' => $waiting && ! $waitedOut ? max(3, (int) config('licensing.return_refresh_seconds', 8)) : null, 'waitedOut' => $waiting && $waitedOut,
            'seats' => $this->ledger->summary($team), 'isOwner' => Gate::check('changeLicence', $team),
        ])->with([]);
    }

    /** The page that says who loses access and when, before an owner action happens. */
    public function confirm(Request $request, Team $team, string $action): View
    {
        $this->authorise($request, $team, true);
        $plan = $this->plan($request, $team, $action);

        return view('licences.confirm', ['team' => $team, 'action' => $action] + $plan);
    }

    public function perform(Request $request, Team $team, string $action): RedirectResponse
    {
        $this->authorise($request, $team, true);
        $plan = $this->plan($request, $team, $action);
        $owner = $request->user();
        $back = redirect(route('teams.show', $team).'#seats');

        try {
            match ($action) {
                'release' => $this->assignments->release($team, $owner, $plan['person']),
                'assign' => $this->assignments->assign($team, $owner, $plan['person']),
                'reassign' => $this->assignments->reassign($team, $owner, $plan['person'], $plan['to']),
                'reduce' => $this->batches->changeAtPeriodEnd($plan['batch'], $owner, (int) $plan['quantity']),
                'cancel' => $this->batches->changeAtPeriodEnd($plan['batch'], $owner, null),
                default => $this->batches->cancelPending($plan['batch'], $owner),   // close
            };
        } catch (LicenceRefused $e) {
            return $back->with('licence_error', $e->getMessage());
        }

        return $back->with('licence_ok', $plan['done']);
    }

    /**
     * Reads and checks what an action is about (always inside this company), and the consequences to show. Anything outside the company is a 404.
     *
     * @return array<string, mixed>
     */
    private function plan(Request $request, Team $team, string $action): array
    {
        abort_unless(in_array($action, self::ACTIONS, true), 404);
        $person = fn (string $field) => $this->member($team, (string) $request->input($field, $request->query($field)));
        $batch = fn () => LicenceBatch::where('team_id', $team->getKey())->where('uuid', (string) $request->input('batch', $request->query('batch')))->firstOrFail();
        $summary = $this->ledger->summary($team);
        $over = fn (int $fewer) => max(0, $this->assignments->licensedCount($team) - ($summary['capacity'] - $fewer));
        $graceDays = max(1, (int) config('licensing.grace_days', 14));

        switch ($action) {
            case 'release':
                $p = $person('user');

                return ['person' => $p, 'title' => "Take the licence from {$p->name}?", 'done' => "{$p->name} no longer holds a licence.", 'button' => 'Take the licence back', 'danger' => true, 'terms' => [
                    "{$p->name} loses access to {$team->name} on InfoDot and on every platform it has switched on, straight away.",
                    "Their account, their content and their place in {$team->name} stay as they are. Nothing is deleted.",
                    'The licence becomes free. You can give it to someone else, or back to them, at any time.',
                ]];
            case 'assign':
                $p = $person('user');

                return ['person' => $p, 'title' => "Give {$p->name} a licence?", 'done' => "{$p->name} holds a licence again.", 'button' => 'Give the licence', 'danger' => false, 'terms' => [
                    "{$p->name} can use {$team->name} on InfoDot and on every platform it has switched on, straight away.",
                    $summary['available'] > 0 || $this->assignments->licensedCount($team) < $summary['capacity']
                        ? 'This uses one of the licences you have free. Nothing is charged.'
                        : 'There is no free licence right now, so this will be refused. Buy another, or take one back from someone first.',
                ]];
            case 'reassign':
                $from = $person('user');
                $to = $person('to');
                abort_if($from->is($to), 404);

                return ['person' => $from, 'to' => $to, 'title' => "Move a licence from {$from->name} to {$to->name}?", 'done' => "The licence moved from {$from->name} to {$to->name}.", 'button' => 'Move the licence', 'danger' => true, 'terms' => [
                    "{$from->name} loses access to {$team->name} on InfoDot and every platform it has switched on, straight away.",
                    "{$to->name} gains that access, straight away.",
                    'Both happen together or neither does. Nothing is charged and nothing is deleted.',
                ]];
            case 'reduce':
            case 'cancel':
                $b = $batch();
                $quantity = $action === 'reduce' ? (int) $request->input('quantity', $request->query('quantity', 0)) : 0;
                abort_if($action === 'reduce' && ($quantity < 1 || $quantity >= $b->quantity), 404);
                $fewer = $b->quantity - $quantity;
                $at = $b->period_end?->timezone(config('app.timezone'))->format('j F Y') ?? 'the end of the paid period';
                $names = $this->overview->whoWouldLose($team, $over($fewer))->pluck('name')->all();
                $lapseOn = $b->period_end?->copy()->addDays($graceDays)->timezone(config('app.timezone'))->format('j F Y');
                $enabled = $this->finance->cancelEnabled();

                return ['batch' => $b, 'quantity' => $quantity, 'title' => $action === 'reduce' ? "Renew {$b->quantity} licences as {$quantity}?" : "Stop these {$b->quantity} licences renewing?", 'done' => $action === 'reduce' ? "From {$at} this purchase renews with {$quantity}." : "These licences stop renewing. They keep working until {$at}.", 'button' => $action === 'reduce' ? 'Reduce at renewal' : 'Stop renewing', 'danger' => true, 'unavailable' => ! $enabled, 'terms' => array_filter([
                    "Nothing changes today. The {$b->quantity} licences stay in force until {$at}, which is the end of what you have paid for.",
                    count($names) === 0 ? 'Everyone who holds a licence now is still covered afterwards, so nobody loses access.'
                        : 'On '.$at.' this leaves '.count($names).' '.(count($names) === 1 ? 'person' : 'people').' without a licence. They keep it for another '.$graceDays." days (until {$lapseOn}) so you can buy more or choose who to take one from. After that, {$this->joinNames($names)} lose".(count($names) === 1 ? 's' : '').' access. You can choose differently before then by taking a licence back from someone yourself.',
                    'The owner and administrators never lose a licence. Nothing is deleted, and anyone who loses access can have it back by buying a licence.',
                    $enabled ? null : 'This cannot be done online yet, so nothing will be changed. To stop or reduce a purchase before its renewal, ask us'.(filled(config('licensing.reminders.support_email')) ? ' at '.config('licensing.reminders.support_email') : '').'.',
                ])];
            default:   // close
                $b = $batch();
                abort_unless($b->status === LicenceBatch::PENDING, 404);

                return ['batch' => $b, 'title' => 'Close this unpaid purchase?', 'done' => 'The unpaid purchase was closed. Nothing was charged.', 'button' => 'Close the purchase', 'danger' => false, 'terms' => [
                    "This purchase of {$b->quantity} licences was started and has not been paid. It holds no licence and nobody's access changes.",
                    'If you already paid and the confirmation is only late, do not close it: it activates by itself once the payment service confirms.',
                ]];
        }
    }

    private function joinNames(array $names): string
    {
        $names = array_values($names);

        return count($names) <= 1 ? ($names[0] ?? 'nobody') : implode(', ', array_slice($names, 0, -1)).' and '.end($names);
    }

    /** A member of this company by id; anyone else is a 404. */
    private function member(Team $team, string $id): User
    {
        /** @var User $user */
        $user = $team->users()->whereKey((int) $id)->firstOrFail();

        return $user;
    }

    /** Batch uuid from `?batch=` or from Paystack's `reference` (`lic_<uuid without dashes>[_n]`, which Dot.Finance sets). */
    private function batchFromReturn(Request $request): ?LicenceBatch
    {
        $uuid = (string) $request->query('batch', '');

        if ($uuid === '' && preg_match('/\Alic_([0-9a-f]{32})(?:_\d{1,3})?\z/i', (string) ($request->query('reference') ?: $request->query('trxref')), $m) === 1) {
            $uuid = Str::lower(preg_replace('/\A(.{8})(.{4})(.{4})(.{4})(.{12})\z/', '$1-$2-$3-$4-$5', $m[1]));
        }

        return preg_match('/\A[0-9a-f-]{36}\z/i', $uuid) === 1 ? LicenceBatch::with('team')->where('uuid', Str::lower($uuid))->first() : null;
    }

    private function authorise(Request $request, Team $team, bool $ownerOnly): void
    {
        abort_if($team->personal_team, 404);
        abort_unless($request->user()->belongsToTeam($team), 404);
        Gate::authorize($ownerOnly ? 'changeLicence' : 'viewLicence', $team);
    }
}

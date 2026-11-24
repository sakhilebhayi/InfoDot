<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamLicence;

/**
 * What a purchase of extra licences costs, worked out on the server from configuration only (R-53 decisions D1 to D3, D12). Nothing is
 * defaulted: with no price configured there is no quote and nothing can be bought. A browser never supplies a price or a total.
 *
 * Volume bands are marginal and count the company's paid licences already covered: buying 3 when 4 are held prices licences 5 to 7, so
 * splitting a purchase into batches does not change the total, and a large batch never reprices licences already bought.
 */
class LicencePricing
{
    /**
     * @return array{unit_price_minor: int, total_minor: int, currency: string, snapshot: array<string, mixed>}
     *
     * @throws LicenceRefused
     */
    public function quote(Team $team, int $quantity, string $interval, ?int $alreadyHeld = null): array
    {
        if (! in_array($interval, LicenceBatch::INTERVALS, true)) {
            throw new LicenceRefused('Choose monthly or yearly billing.');
        }

        if ($quantity < 1) {
            throw new LicenceRefused('Choose at least one licence.');
        }

        $unit = config($interval === LicenceBatch::YEAR ? 'licensing.price.annual_amount_minor_units' : 'licensing.price.amount_minor_units');
        $currency = config('licensing.price.currency');

        if (! filled($unit) || ! filled($currency) || (int) $unit < 1) {
            throw new LicenceRefused('Paid licences cannot be bought just now: no price has been set. Nothing has been charged.');
        }

        $held = $alreadyHeld ?? $this->heldBy($team);
        $maxExtra = max(0, (int) config('licensing.max_online_users', 100) - 1);

        if ($held + $quantity > $maxExtra) {
            throw new LicenceRefused('Above '.config('licensing.max_online_users', 100).' people a company is on a custom agreement. Contact us to arrange it.');
        }

        $bands = $this->bands();
        $total = $this->cost($held + $quantity, (int) $unit, $bands) - $this->cost($held, (int) $unit, $bands);

        return [
            'unit_price_minor' => intdiv($total + intdiv($quantity, 2), $quantity),
            'total_minor' => $total,
            'currency' => (string) $currency,
            'snapshot' => ['base_unit_minor' => (int) $unit, 'bands' => $bands, 'already_held' => $held, 'quantity' => $quantity, 'interval' => $interval, 'currency' => (string) $currency, 'vat' => 'excluded'],
        ];
    }

    /**
     * What `$count` paid licences cost together, from configuration (ex VAT), or null when no price is set. Shown on the licences screen as
     * "what this would cost monthly / yearly"; the amount actually charged is each batch's own `total_minor`.
     *
     * @return array{total_minor: int, currency: string}|null
     */
    public function costOf(int $count, string $interval): ?array
    {
        $unit = config($interval === LicenceBatch::YEAR ? 'licensing.price.annual_amount_minor_units' : 'licensing.price.amount_minor_units');
        $currency = config('licensing.price.currency');

        if (! filled($unit) || ! filled($currency) || (int) $unit < 1 || $count < 1) {
            return null;
        }

        return ['total_minor' => $this->cost($count, (int) $unit, $this->bands()), 'currency' => (string) $currency];
    }

    /** The paid licences the company holds now (batches that count today, plus any legacy purchased seats). */
    public function heldBy(Team $team): int
    {
        return app(SeatLedger::class)->batchSeats($team) + (int) TeamLicence::where('team_id', $team->getKey())->value('purchased_seats');
    }

    /**
     * The marginal cost of `$count` licences at `$unit` per licence.
     *
     * @param  list<array{up_to: ?int, percent: int}>  $bands
     */
    private function cost(int $count, int $unit, array $bands): int
    {
        $total = 0;
        $from = 0;

        foreach ($bands as $band) {
            if ($count <= $from) {
                break;
            }

            $to = $band['up_to'] === null ? $count : min($count, $band['up_to']);
            $total += (int) round(($to - $from) * $unit * $band['percent'] / 100);
            $from = $to;
        }

        return $total;
    }

    /** @return list<array{up_to: ?int, percent: int}> */
    private function bands(): array
    {
        $bands = [];

        foreach ((array) config('licensing.price.bands', []) as $band) {
            $bands[] = ['up_to' => isset($band['up_to']) ? (int) $band['up_to'] : null, 'percent' => max(1, min(100, (int) ($band['percent'] ?? 100)))];
        }

        return $bands === [] ? [['up_to' => null, 'percent' => 100]] : $bands;
    }

    /** One plain sentence about the volume bands, from configuration. */
    public function bandText(): ?string
    {
        $bands = (array) config('licensing.price.bands', []);
        $parts = [];
        $from = 1;

        foreach ($bands as $band) {
            $percent = (int) ($band['percent'] ?? 100);

            if ($percent < 100) {
                $parts[] = "from licence {$from} onwards you pay {$percent}% of the price for each licence";
            }

            if (isset($band['up_to'])) {
                $from = (int) $band['up_to'] + 1;
            }
        }

        return $parts === [] ? null : 'The more licences you hold, the less each extra one costs: '.implode('; ', $parts).'.';
    }
}

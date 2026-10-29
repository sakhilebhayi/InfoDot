<?php

namespace App\Services\Seminars;

/**
 * How many people a peer-to-peer room can hold, worked out from the host's measured upload speed and the quality they chose. Pure maths, no
 * database. The host feeds as many viewers directly as their upload (and their computer, which encodes once per viewer) allows; each of those
 * viewers who can pass the stream on feeds a few more, and so on for a few hops. The promised number is a cautious share of that, because
 * real viewers drop out, sit behind strict networks and use phones. It is an estimate and the page says so.
 */
class P2pCapacity
{
    /**
     * @return array{quality: string, label: string, direct: int, estimate: int, capacity: int, depth: int, usable: bool}
     */
    public static function plan(int $uplinkKbps, string $quality): array
    {
        $cfg = config('seminars.p2p');
        $q = $cfg['qualities'][$quality] ?? null;

        if ($q === null) {
            return ['quality' => $quality, 'label' => $quality, 'direct' => 0, 'estimate' => 0, 'capacity' => 0, 'depth' => 0, 'usable' => false];
        }

        $direct = min((int) $q['host_direct_max'], (int) floor($uplinkKbps * (float) $cfg['host_uplink_share'] / max(1, (int) $q['kbps'])));

        if ($direct < 1) {
            return ['quality' => $quality, 'label' => $q['label'], 'direct' => 0, 'estimate' => 0, 'capacity' => 0, 'depth' => 0, 'usable' => false];
        }

        $level = $direct;
        $total = $direct;
        $depth = 1;

        while ($depth < (int) $cfg['max_depth']) {
            $level = (int) floor($level * (float) $q['relay_ratio'] * (int) $cfg['relay_slots']);

            if ($level < 1) {
                break;
            }

            $total += $level;
            $depth++;
        }

        $max = (int) $cfg['max_viewers'];
        $capacity = max(1, min($max, (int) floor($total * (float) $cfg['safety_factor'])));

        return ['quality' => $quality, 'label' => $q['label'], 'direct' => $direct, 'estimate' => min($max, $total), 'capacity' => $capacity, 'depth' => $depth, 'usable' => true];
    }

    /** @return array<string, array{quality: string, label: string, direct: int, estimate: int, capacity: int, depth: int, usable: bool}> every quality for one speed, for the form */
    public static function all(int $uplinkKbps): array
    {
        return collect(array_keys(config('seminars.p2p.qualities')))->mapWithKeys(fn ($q) => [$q => self::plan($uplinkKbps, $q)])->all();
    }
}

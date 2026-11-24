<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GrowthTarget;
use App\Models\ReferralEvent;
use App\Models\ReferralTier;
use App\Services\Growth\GrowthMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** R-59 Phase 1D: the Growth Centre. Platform administrators only; every figure is counted from real records. */
class GrowthCentreController extends Controller
{
    private const TABS = ['overview' => 'Overview', 'funnel' => 'Funnel', 'channels' => 'Channels', 'geography' => 'Geography', 'platforms' => 'Platforms', 'referrals' => 'Referrals', 'targets' => 'Targets'];

    public function show(Request $request, GrowthMetrics $metrics, string $tab = 'overview'): View
    {
        abort_unless(isset(self::TABS[$tab]), 404);

        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null;

        return view('admin.growth', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'overview' => $tab === 'overview' ? $metrics->overview($from, $to) : null,
            'unmeasured' => $metrics->unmeasured(),
            'funnel' => $tab === 'funnel' ? $metrics->funnel($from, $to) : null,
            'channels' => $tab === 'channels' ? $metrics->channels($from, $to) : null,
            'geography' => $tab === 'geography' ? $metrics->geography() : null,
            'platforms' => $tab === 'platforms' ? $metrics->platforms() : null,
            'referrals' => $tab === 'referrals' ? $metrics->referrals() : null,
            'targets' => $tab === 'targets' ? $metrics->targets() : null,
        ]);
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.value' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'targets.*.date' => ['nullable', 'date'],
        ]);

        foreach (GrowthTarget::METRICS as $metric => $label) {
            $row = $data['targets'][$metric] ?? [];
            if (empty($row['value'])) {
                GrowthTarget::where('metric', $metric)->delete();

                continue;
            }
            GrowthTarget::updateOrCreate(['metric' => $metric], ['value' => (int) $row['value'], 'target_date' => $row['date'] ?? null, 'set_by' => $request->user()->id]);
        }

        return redirect()->route('admin.growth', ['tab' => 'targets'])->with('status', 'Targets saved. They are plans, not results.');
    }

    public function updateTiers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tiers' => ['required', 'array'],
            'tiers.*.points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'tiers.*.active' => ['nullable', 'boolean'],
        ]);

        foreach (ReferralTier::all() as $tier) {
            $row = $data['tiers'][$tier->id] ?? null;
            if ($row === null) {
                continue;
            }
            $tier->forceFill(['points' => (int) $row['points'], 'active' => (bool) ($row['active'] ?? false)])->save();
            ReferralEvent::create(['type' => 'tier_changed', 'reason' => $tier->referrals_required.' referrals: '.$tier->points.' points, '.($tier->active ? 'on' : 'off'), 'actor_id' => $request->user()->id]);
        }

        // Rewards already earned keep the amount they were created with; a changed table affects rewards created from now on.
        return redirect()->route('admin.growth', ['tab' => 'referrals'])->with('status', 'Reward tiers saved. Rewards already earned keep their amount.');
    }
}

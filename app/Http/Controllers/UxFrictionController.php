<?php

namespace App\Http\Controllers;

use App\Services\Ux\FrictionReport;
use App\Services\Ux\UxSignals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Moderators only (route middleware). Read-only: counts and route templates, never a person. */
class UxFrictionController extends Controller
{
    public function index(Request $request, FrictionReport $report, UxSignals $signals): View
    {
        $days = in_array((int) $request->query('days', 14), [7, 14, 30, 90], true) ? (int) $request->query('days', 14) : 14;

        return view('moderation.friction', ['report' => $report->build($days), 'days' => $days, 'enabled' => $signals->enabled()]);
    }
}

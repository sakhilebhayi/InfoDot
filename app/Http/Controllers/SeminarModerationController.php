<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\SeminarRemovalAppeal;
use App\Models\SeminarReport;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Moderators only (route middleware): reports about seminars, and removing a seminar. */
class SeminarModerationController extends Controller
{
    public function __construct(private readonly SeminarService $seminars) {}

    public function index(): View
    {
        return view('seminars.moderation', [
            'reports' => SeminarReport::with(['seminar', 'reporter'])->where('status', 'open')->orderBy('id')->get()->groupBy('seminar_id'),
            'recent' => SeminarReport::with(['seminar', 'reporter'])->where('status', '!=', 'open')->orderByDesc('id')->limit(15)->get(),
            'appeals' => SeminarRemovalAppeal::with('seminar')->where('status', SeminarRemovalAppeal::OPEN)->orderBy('id')->get(),
            'registrants' => fn (Seminar $s, ?string $name) => $name === null || trim($name) === '' ? collect() : User::whereIn('id', SeminarRegistration::where('seminar_id', $s->id)->where('status', '!=', SeminarRegistration::CANCELLED)->pluck('user_id'))
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower(trim($name)).'%'])->limit(5)->get(['id', 'name']),
        ]);
    }

    public function remove(Request $request, Seminar $seminar): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500'], 'refunds' => ['nullable', 'in:all,complainants,none']]);
        $this->seminars->removeByModerator($seminar, $request->user(), $data['reason'], $data['refunds'] ?? 'all');

        return back()->with('seminar_notice', 'The seminar was removed. People who registered were told, anyone who paid is being refunded, and the host was told why.');
    }

    public function decideAppeal(Request $request, SeminarRemovalAppeal $appeal): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:reinstate,deny'], 'note' => ['required', 'string', 'min:5', 'max:500']]);
        $this->seminars->decideRemovalAppeal($appeal, $request->user(), $data['decision'] === 'reinstate', $data['note']);

        return back()->with('seminar_notice', $data['decision'] === 'reinstate' ? 'The seminar is back on and the people registered were told.' : 'The appeal was denied and the host was told.');
    }

    public function bar(Request $request, Seminar $seminar, int $person): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);
        abort_unless(SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $person)->exists(), 404);
        $this->seminars->barFromRoom($seminar, User::findOrFail($person), $request->user(), $data['note']);

        return back()->with('seminar_notice', 'They were barred from the room. They stay registered.');
    }

    public function dismiss(Request $request, SeminarReport $report): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);
        abort_unless($report->status === 'open', 404);
        $this->seminars->dismissReport($report, $request->user(), $data['note']);

        return back()->with('seminar_notice', 'The report was dismissed.');
    }
}

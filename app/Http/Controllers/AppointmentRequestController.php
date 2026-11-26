<?php

namespace App\Http\Controllers;

use App\Models\BookingSetting;
use App\Models\Enquiry;
use App\Models\Team;
use App\Services\Enquiries\AppointmentRequests;
use App\Support\ActingCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * R-66 phase 3: "Request an appointment" on the page of a business with no published calendar, and
 * the business's answer from its inbox. The rules live in AppointmentRequests; this only reads the form.
 */
class AppointmentRequestController extends Controller
{
    public function __construct(private AppointmentRequests $requests) {}

    public function create(Request $request, Team $team): View
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        return view('inbox.request-appointment', [
            'team' => $team,
            'reason' => $this->requests->whyNot($request->user(), $team),
            'companies' => ActingCompany::options($request->user(), $team),
            'company' => ActingCompany::preselected($request->user(), $team),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        $data = $request->validate([
            'on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'part_of_day' => ['required', Rule::in(array_merge(array_keys(Enquiry::PARTS_OF_DAY), ['set']))],
            'set_time' => ['nullable', 'required_if:part_of_day,set', 'date_format:H:i'],
            'minutes' => ['required', 'integer', Rule::in(Enquiry::LENGTHS)],
            'about' => ['required', 'string', 'min:'.config('enquiries.min_length'), 'max:'.config('enquiries.max_length')],
            ActingCompany::FIELD => ['nullable', 'string', 'max:20'],
        ], [
            'on.after_or_equal' => 'Choose today or a later date.',
            'on.before_or_equal' => 'Choose a date within the next year.',
            'set_time.required_if' => 'Give the time you would like, or choose a part of the day.',
            'about.min' => 'Say a little more, at least :min characters, so the business knows what the meeting is for.',
        ]);

        $time = $data['part_of_day'] === 'set' ? $data['set_time'] : $data['part_of_day'];

        $enquiry = $this->requests->request($request->user(), $team, [
            'on' => $data['on'],
            'time' => $time,
            'minutes' => (int) $data['minutes'],
            'about' => $data['about'],
            'company_team' => ActingCompany::resolve($request->user(), $team, $data[ActingCompany::FIELD] ?? null),
        ]);

        return redirect()->route('inbox.show', $enquiry)->with('status', 'Request sent to '.$team->name.'. Their answer arrives here, and the bell tells you when it does.');
    }

    /** Accept with a date and time (read in the business's own time zone), or decline with a reason. Owners, administrators and the member it is given to may answer. */
    public function decide(Request $request, Team $team, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($enquiry->team_id === $team->id && $request->user()->can('handle', $enquiry) && $enquiry->isAppointmentRequest(), 404);

        $data = $request->validate([
            'decision' => ['required', 'in:accept,decline'],
            'on' => ['exclude_unless:decision,accept', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'at' => ['exclude_unless:decision,accept', 'required', 'date_format:H:i'],
            'minutes' => ['exclude_unless:decision,accept', 'required', 'integer', Rule::in(Enquiry::LENGTHS)],
            'note' => ['exclude_unless:decision,accept', 'nullable', 'string', 'max:1000'],
            'reason' => ['exclude_unless:decision,decline', 'required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => 'Give the person a reason, so they know what to do next.',
        ]);

        if ($data['decision'] === 'accept') {
            $this->requests->accept($enquiry, $request->user(), Carbon::parse($data['on'].' '.$data['at'], BookingSetting::forTeam($team)->zone()), (int) $data['minutes'], $data['note'] ?? null);
            $status = 'Accepted. '.($enquiry->customer->name ?? 'The person').' has been told.';
        } else {
            $this->requests->decline($enquiry, $request->user(), $data['reason']);
            $status = 'Declined. '.($enquiry->customer->name ?? 'The person').' has been told why.';
        }

        return redirect()->route('business.inbox.show', [$team, $enquiry])->withFragment('latest')->with('status', $status);
    }
}

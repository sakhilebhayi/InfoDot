<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\Team;
use App\Services\Enquiries\EnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The customer's side of the InfoDot Inbox (R-33 §5A): "Message business" on a business page,
 * the person's own enquiries, and each conversation. A person only ever reaches their own
 * enquiries here; someone who handles an enquiry for the business is sent to the business inbox.
 */
class EnquiryController extends Controller
{
    public function __construct(private EnquiryService $enquiries) {}

    public function create(Request $request, Team $team): View
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        return view('inbox.create', [
            'team' => $team,
            'reason' => $this->enquiries->whyNot($request->user(), $team),
            'categories' => config('enquiries.categories'),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'category' => ['required', Rule::in(array_keys(config('enquiries.categories')))],
            'body' => ['required', 'string', 'min:'.config('enquiries.min_length'), 'max:'.config('enquiries.max_length')],
            'files' => ['nullable', 'array', 'max:'.config('enquiries.max_files')],
            'files.*' => ['file'],
        ], ['body.min' => 'Say a little more — at least :min characters — so the business can help.']);

        $enquiry = $this->enquiries->start($request->user(), $team, $data, $request->file('files', []));

        return redirect()->route('inbox.show', $enquiry)->with('status', 'Sent to '.$team->name.'. Replies arrive here, and the bell tells you when one does.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $mine = Enquiry::where('user_id', $user->id)->with(['team.logo'])->latest('updated_at')->paginate(20);

        return view('inbox.index', [
            'enquiries' => $mine,
            'reads' => $this->enquiries->readTimes($mine, $user),
            // Businesses whose inbox this person works in, with how many conversations wait for them.
            'businesses' => $this->enquiries->handledQuery($user)->with('team')->get()->groupBy('team_id')
                ->map(fn (Collection $group) => ['team' => $group->first()?->team, 'active' => $group->whereNotIn('status', [Enquiry::RESOLVED, Enquiry::CLOSED])->count()])
                ->filter(fn ($row) => $row['team'] !== null)->values(),
        ]);
    }

    public function show(Request $request, Enquiry $enquiry): View|RedirectResponse
    {
        $user = $request->user();

        if (! $enquiry->isCustomer($user)) {
            abort_unless($enquiry->isHandler($user), 404);

            return redirect()->route('business.inbox.show', [$enquiry->team, $enquiry]);
        }

        $messages = $enquiry->messages()->whereIn('kind', [EnquiryMessage::CUSTOMER, EnquiryMessage::BUSINESS])->get();
        $this->enquiries->markRead($enquiry, $user);

        return view('inbox.show', [
            'enquiry' => $enquiry->load('team.logo'),
            'messages' => $messages,
            'files' => $this->enquiries->filesFor($messages),
        ]);
    }

    public function reply(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($enquiry->isCustomer($request->user()), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.config('enquiries.max_length')],
            'files' => ['nullable', 'array', 'max:'.config('enquiries.max_files')],
            'files.*' => ['file'],
        ]);

        $this->enquiries->reply($enquiry, $request->user(), $data['body'], $request->file('files', []));

        return redirect()->route('inbox.show', $enquiry)->withFragment('latest')->with('status', 'Sent.');
    }
}

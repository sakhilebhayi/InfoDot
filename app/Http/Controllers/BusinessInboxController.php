<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\EnquiryBlock;
use App\Models\Team;
use App\Models\User;
use App\Services\Enquiries\EnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The business's side of the InfoDot Inbox (R-33 §5A, enquiry management). The owner and
 * administrators see every enquiry, assign them, block senders and read the numbers; a member
 * sees only what is assigned to them. Nobody else reaches these pages, and an enquiry is only
 * ever opened through its own business's address.
 */
class BusinessInboxController extends Controller
{
    public function __construct(private EnquiryService $enquiries) {}

    public function index(Request $request, Team $team): View
    {
        $user = $request->user();
        abort_if($team->personal_team || ! $user->belongsToTeam($team), 403);
        $manager = Gate::allows('updateProfile', $team);

        $f = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(Enquiry::STATUSES), ['active', 'all']))],
            'who' => ['nullable', 'in:mine,unassigned'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
        $status = $f['status'] ?? 'active';

        $list = $team->enquiries()->with(['customer:id,name', 'assignee:id,name'])
            ->when(! $manager, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(! in_array($status, ['active', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when(($f['who'] ?? null) === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when(($f['who'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($f['q'] ?? null, fn ($q, $words) => $q->where('subject', 'like', '%'.$words.'%'))
            ->latest('updated_at')->paginate(25)->withQueryString();

        return view('inbox.business-index', [
            'team' => $team,
            'enquiries' => $list,
            'reads' => $this->enquiries->readTimes($list, $user),
            'manager' => $manager,
            'f' => $f + ['status' => $status],
            'stats' => $manager ? $this->enquiries->stats($team) : null,
        ]);
    }

    public function show(Request $request, Team $team, Enquiry $enquiry): View
    {
        $this->guard($request, $team, $enquiry);
        $user = $request->user();

        $messages = $enquiry->messages()->with('author:id,name')->get();
        $this->enquiries->markRead($enquiry, $user);
        $manager = Gate::allows('updateProfile', $team);

        return view('inbox.business-show', [
            'team' => $team,
            'enquiry' => $enquiry->load(['customer:id,name', 'assignee:id,name']),
            'messages' => $messages,
            'files' => $this->enquiries->filesFor($messages),
            'manager' => $manager,
            'members' => $manager ? $team->allUsers()->sortBy('name')->values() : collect(),
            'blocked' => $enquiry->user_id !== null && EnquiryBlock::where('team_id', $team->id)->where('user_id', $enquiry->user_id)->exists(),
        ]);
    }

    public function reply(Request $request, Team $team, Enquiry $enquiry): RedirectResponse
    {
        $this->guard($request, $team, $enquiry);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.config('enquiries.max_length')],
            'internal' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:'.config('enquiries.max_files')],
            'files.*' => ['file'],
        ]);

        if ($enquiry->customer === null && ! ($data['internal'] ?? false)) {
            return back()->withErrors(['body' => 'The person who sent this no longer has an InfoDot account, so only internal notes can be added.']);
        }

        $this->enquiries->reply($enquiry, $request->user(), $data['body'], $request->file('files', []), (bool) ($data['internal'] ?? false));

        return $this->back($team, $enquiry, ($data['internal'] ?? false) ? 'Note added. The customer does not see notes.' : 'Reply sent to '.($enquiry->customer->name ?? 'the customer').'.');
    }

    /** Status, follow-up date and — for the owner and administrators — who has it. */
    public function update(Request $request, Team $team, Enquiry $enquiry): RedirectResponse
    {
        $this->guard($request, $team, $enquiry);
        $user = $request->user();
        $manager = Gate::allows('updateProfile', $team);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Enquiry::STATUSES))],
            'assigned_to' => ['nullable', 'integer'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
            'clear_follow_up' => ['nullable', 'boolean'],
        ]);

        if (isset($data['status'])) {
            $this->enquiries->setStatus($enquiry, $user, $data['status']);
        }

        if ($manager && $request->has('assigned_to')) {
            $assignee = filled($data['assigned_to'] ?? null) ? User::find($data['assigned_to']) : null;
            abort_if(filled($data['assigned_to'] ?? null) && $assignee === null, 422);
            $this->enquiries->assign($enquiry, $user, $assignee);
        }

        if ($request->boolean('clear_follow_up') && $enquiry->follow_up_on !== null) {
            $this->enquiries->setFollowUp($enquiry, $user, null);
        } elseif (filled($data['follow_up_on'] ?? null) && ! $enquiry->follow_up_on?->isSameDay($data['follow_up_on'])) {
            $this->enquiries->setFollowUp($enquiry, $user, Carbon::parse($data['follow_up_on']));
        }

        return $this->back($team, $enquiry, 'Saved.');
    }

    public function block(Request $request, Team $team, Enquiry $enquiry): RedirectResponse
    {
        $this->guard($request, $team, $enquiry);
        Gate::authorize('updateProfile', $team);
        abort_if($enquiry->customer === null, 404);

        if ($request->boolean('unblock')) {
            $this->enquiries->unblock($team, $enquiry->customer);

            return $this->back($team, $enquiry, $enquiry->customer->name.' can open enquiries with '.$team->name.' again.');
        }

        $this->enquiries->block($team, $request->user(), $enquiry->customer);

        return $this->back($team, $enquiry, $enquiry->customer->name.' can no longer open new enquiries with '.$team->name.'. This conversation stays as it is.');
    }

    /** The enquiry must be this business's, and the person must handle it. Anything else is not found. */
    private function guard(Request $request, Team $team, Enquiry $enquiry): void
    {
        abort_unless($enquiry->team_id === $team->id && $enquiry->isHandler($request->user()), 404);
    }

    private function back(Team $team, Enquiry $enquiry, string $status): RedirectResponse
    {
        return redirect()->route('business.inbox.show', [$team, $enquiry])->withFragment('latest')->with('status', $status);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountSuspensionEvent;
use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The accounts desk (R-49), for moderators (route middleware): find a person by name or email, read their
 * suspension trail, and suspend or restore the account. Until this existed an abusive account could only be
 * switched off from a shell. The page never lists everyone: without a search it shows the newest sign-ups only.
 * Who may act on whom is AccountSuspension::whyNot(), asked again on every action.
 */
class AccountsDeskController extends Controller
{
    private const RECENT = 15;

    private const PER_PAGE = 20;

    public function __construct(private readonly AccountSuspension $suspension) {}

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $q = trim((string) ($data['q'] ?? ''));
        $searching = mb_strlen($q) >= 2;

        $people = User::query()->with(['ownedTeams', 'teams']);

        if ($searching) {
            // The person's words are matched literally: % and _ are not wildcards here ("!" escapes them on MySQL and SQLite alike).
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $people = $people->where(fn ($w) => $w->whereRaw("name like ? escape '!'", [$like])->orWhereRaw("email like ? escape '!'", [$like]))
                ->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        } else {
            $people = $people->latest('created_at')->latest('id')->limit(self::RECENT)->get();
        }

        return view('moderation.accounts', [
            'q' => $q,
            'searching' => $searching,
            'people' => $people,
            'suspended' => User::whereNotNull('suspended_at')->count(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        return view('moderation.account', [
            'account' => $user->load(['ownedTeams', 'teams']),
            'events' => AccountSuspensionEvent::with('actor')->where('user_id', $user->id)->orderByDesc('id')->get(),
            'refusal' => $this->suspension->whyNot($request->user(), $user),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->allowed($request, $user);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'confirm' => ['accepted'],
        ], [
            'reason.required' => 'Write why the account is being suspended. It is kept in the trail.',
            'reason.min' => 'Say a little more about why: at least 10 characters.',
            'confirm.accepted' => 'Tick the box to confirm you mean to suspend this account.',
        ]);

        if ($user->isSuspended()) {
            return redirect()->route('moderation.accounts.show', $user)->with('status', $user->name.' is already suspended. Nothing was changed.');
        }

        $ended = $this->suspension->suspend($user, $data['reason'], $request->user());

        return redirect()->route('moderation.accounts.show', $user)->with('status', sprintf(
            '%s is suspended and cannot sign in. Ended: %d browser %s, %d API %s, sign-ins on %d %s.',
            $user->name,
            $ended['sessions'], $ended['sessions'] === 1 ? 'session' : 'sessions',
            $ended['api_tokens'], $ended['api_tokens'] === 1 ? 'token' : 'tokens',
            $ended['platforms'], $ended['platforms'] === 1 ? 'platform' : 'platforms',
        ));
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        $this->allowed($request, $user);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if (! $user->isSuspended()) {
            return redirect()->route('moderation.accounts.show', $user)->with('status', $user->name.' is not suspended. Nothing was changed.');
        }

        $this->suspension->restore($user, $data['reason'] ?? null, $request->user());

        return redirect()->route('moderation.accounts.show', $user)->with('status', $user->name.' can sign in again. They start with a fresh sign-in: nothing that was ended comes back.');
    }

    /** The server-side half of the rules the page explains: 403 with the same sentence the page shows. */
    private function allowed(Request $request, User $user): void
    {
        $refusal = $this->suspension->whyNot($request->user(), $user);

        abort_if($refusal !== null, 403, (string) $refusal);
    }
}

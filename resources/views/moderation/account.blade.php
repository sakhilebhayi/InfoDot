<x-app-layout :title="$account->name">
    @php
        $companies = $account->ownedTeams->merge($account->teams)->where('personal_team', false)->unique('id');
        $suspended = $account->isSuspended();
    @endphp

    @include('livewire.moderation._tabs', ['current' => 'accounts'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $account->name }}</h1>
            <p class="ix-lede">
                @if ($suspended)<span class="ix-chip ix-chip--open">Suspended since {{ $account->suspended_at->format('j M Y') }}</span>@else<span class="ix-chip ix-chip--done">Active</span>@endif
                @if ($account->isModerator())<span class="ix-chip ix-chip--info">Moderator</span>@endif
                <x-demo-mark :of="$account" />
                {{ $account->email }}
            </p>
        </div>
        <a href="{{ route('moderation.accounts') }}" class="ix-btn ix-btn--ghost">All accounts</a>
    </div>

    @if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif
    @if ($refusal)<p class="ix-alert" role="note">{{ $refusal }}</p>@endif

    <div class="ix-vdesk">
        <section class="ix-vdesk__col" aria-labelledby="acc-h">
            <h2 id="acc-h">The account</h2>
            <dl class="ix-biz__facts">
                <div><dt>Name</dt><dd>{{ $account->name }}</dd></div>
                <div><dt>Email</dt><dd>{{ $account->email }} @if ($account->email_verified_at)<span class="ix-chip ix-chip--done">verified</span>@else<span class="ix-chip">not verified</span>@endif</dd></div>
                <div><dt>Joined</dt><dd>{{ $account->created_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                <div><dt>Companies</dt><dd>@forelse ($companies as $company){{ $company->name }} <span class="ix-ref">{{ $company->code }}</span>@if (! $loop->last)<br>@endif @empty None @endforelse</dd></div>
            </dl>
        </section>

        <section class="ix-vdesk__col" aria-labelledby="stand-h">
            <h2 id="stand-h">Standing</h2>
            <dl class="ix-biz__facts">
                <div><dt>Now</dt><dd>{{ $suspended ? 'Suspended: cannot sign in to InfoDot or any Dot platform' : 'Active: can sign in' }}</dd></div>
                @if ($suspended)
                    <div><dt>Since</dt><dd>{{ $account->suspended_at->format('j M Y, H:i') }}</dd></div>
                    <div><dt>Reason</dt><dd>{{ $account->suspended_reason ?: 'None was written down' }}</dd></div>
                @endif
                <div><dt>Times suspended</dt><dd>{{ $events->where('action', \App\Models\AccountSuspensionEvent::SUSPENDED)->count() }}</dd></div>
            </dl>
        </section>
    </div>

    @unless ($refusal)
        <section class="ix-panel ix-vgap" aria-labelledby="act-h">
            <div class="ix-panel__head"><h2 id="act-h">{{ $suspended ? 'Restore the account' : 'Suspend the account' }}</h2></div>
            <div class="ix-panel__body ix-vactions">
                @if ($suspended)
                    <form method="post" action="{{ route('moderation.accounts.restore', $account) }}" class="ix-form">
                        @csrf
                        <p class="ix-hint">{{ $account->name }} will be able to sign in again, starting with a fresh sign-in. The sessions and tokens that were ended do not come back.</p>
                        <div class="ix-field">
                            <label class="ix-label" for="restore-reason">Why it is being restored (optional, kept in the history)</label>
                            <textarea id="restore-reason" name="reason" class="ix-textarea" rows="2" maxlength="255">{{ old('reason') }}</textarea>
                        </div>
                        <div><button type="submit" class="ix-btn ix-btn--primary">Restore the account</button></div>
                    </form>
                @else
                    <details @if ($errors->any()) open @endif>
                        <summary class="ix-btn ix-btn--danger">Suspend…</summary>
                        <form method="post" action="{{ route('moderation.accounts.suspend', $account) }}" class="ix-form ix-vgap">
                            @csrf
                            <p class="ix-hint">What happens when you suspend {{ $account->name }}:</p>
                            <dl class="ix-biz__facts">
                                <div><dt>Sessions</dt><dd>Ended everywhere at once: every browser session, API token and sign-in on a Dot platform.</dd></div>
                                <div><dt>Signing in</dt><dd>They cannot sign in to InfoDot or any Dot platform until a moderator restores the account.</dd></div>
                                <div><dt>Their work</dt><dd>What they wrote stays where it is. Nothing is deleted.</dd></div>
                                <div><dt>The record</dt><dd>Your name, the time and the reason below are kept in the history, which cannot be edited.</dd></div>
                            </dl>
                            <div class="ix-field">
                                <label class="ix-label" for="suspend-reason">Reason (10 to 255 characters)</label>
                                <textarea id="suspend-reason" name="reason" class="ix-textarea" rows="3" minlength="10" maxlength="255" required>{{ old('reason') }}</textarea>
                            </div>
                            <fieldset class="ix-vchecks">
                                <label><input type="checkbox" name="confirm" value="1" required><span>I have read what happens and I mean to suspend this account.</span></label>
                            </fieldset>
                            <div><button type="submit" class="ix-btn ix-btn--danger">Suspend {{ $account->name }}</button></div>
                        </form>
                    </details>
                @endif
            </div>
        </section>
    @endunless

    <section @class(['ix-panel', 'ix-vgap' => (bool) $refusal]) aria-labelledby="hist-h">
        <div class="ix-panel__head"><h2 id="hist-h">Suspension history</h2><span class="ix-hint">{{ $events->count() }}</span></div>
        <div class="ix-panel__body">
            @if ($events->isEmpty())
                <p class="ix-hint">This account has never been suspended.</p>
            @else
                <ol class="ix-vhist">
                    @foreach ($events as $event)
                        <li>
                            <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('j M Y, H:i') }}</time>
                            <span>{{ $event->action === \App\Models\AccountSuspensionEvent::SUSPENDED ? 'Suspended' : 'Restored' }} — {{ $event->actor?->name ?? 'from the command line, or by an account that no longer exists' }}
                                @if ($event->reason)<span class="ix-vhist__note">{{ $event->reason }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</x-app-layout>

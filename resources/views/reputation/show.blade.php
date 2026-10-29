<x-app-layout>
    @php($flash = session('reputation_notice'))
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Your progress</h1>
            <p class="ix-lede">How your knowledge work adds up. Only you see this page: there is no ranking, no public level and no comparison with other people, and a level never gives anyone control of a company.</p>
        </div>
    </div>

    @if ($flash)<p class="ix-pts__muted" role="status">{{ $flash }}</p>@endif

    <section aria-labelledby="tier-h">
        <h2 class="ix-label" id="tier-h">Where you are</h2>
        <p><strong>{{ $result['tier_name'] }}</strong> <span class="ix-chip ix-chip--done">{{ $result['total'] }} points</span></p>
        @if ($result['next'])
            <p class="ix-hint">{{ $result['next']['remaining'] }} more points to reach {{ $result['next']['name'] }} (at {{ $result['next']['needed'] }}).</p>
        @else
            <p class="ix-hint">You are at the highest level.</p>
        @endif
    </section>

    <section aria-labelledby="how-h" style="margin-top: 24px;">
        <h2 class="ix-label" id="how-h">How it is worked out</h2>
        <table class="ix-table">
            <thead><tr><th scope="col">Area</th><th scope="col">What counts</th><th scope="col">Points</th></tr></thead>
            <tbody>
                @foreach ($result['dimensions'] as $d)
                    <tr>
                        <th scope="row">{{ $d['label'] }}</th>
                        <td>{{ $d['what'] }}</td>
                        <td>{{ $d['score'] }} <span class="ix-hint">({{ rtrim(rtrim(number_format($d['points'], 1), '0'), '.') }} of {{ $d['cap'] }}, weighted {{ $d['weight'] }})</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="ix-hint">Only public, published work that was not hidden counts, and never help you gave yourself. Repeated help accepted by the same person counts less each time. Missing a week costs nothing. The rules are version {{ $result['rules_version'] }} and may change as we learn what is fair.</p>
    </section>

    <section aria-labelledby="appeal-h" style="margin-top: 24px;">
        <h2 class="ix-label" id="appeal-h">Think a number is wrong?</h2>
        <form method="POST" action="{{ route('reputation.appeal') }}" class="ix-form">
            @csrf
            <div class="ix-field">
                <label class="ix-label" for="message">Tell a moderator what looks wrong</label>
                <textarea class="ix-textarea" id="message" name="message" rows="3" maxlength="1000" required></textarea>
                @error('message')<p class="ix-error">{{ $message }}</p>@enderror
            </div>
            <button class="ix-btn" type="submit">Send appeal</button>
        </form>
        @if ($appeals->isNotEmpty())
            <ul class="ix-list" style="margin-top: 12px;">
                @foreach ($appeals as $a)
                    <li style="padding: 10px 14px;">{{ $a->created_at->format('j M Y') }}: {{ $a->message }}
                        <span class="ix-chip {{ $a->status === 'open' ? '' : 'ix-chip--done' }}">{{ $a->status === 'open' ? 'Waiting' : 'Reviewed' }}</span>
                        @if ($a->resolution_note)<br><span class="ix-hint">Moderator: {{ $a->resolution_note }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="own-h" style="margin-top: 24px;">
        <h2 class="ix-label" id="own-h">Your data</h2>
        <p class="ix-hint">You can keep a copy of everything stored about your score, or switch it off. Switching off deletes what is stored and nothing is worked out for you until you switch it back on.</p>
        <a class="ix-btn" href="{{ route('reputation.export') }}">Download my score data</a>
        <form method="POST" action="{{ route('reputation.preference') }}" style="display: inline;">
            @csrf
            <input type="hidden" name="reputation" value="0">
            <button class="ix-btn" type="submit">Switch my score off</button>
        </form>
    </section>
</x-app-layout>

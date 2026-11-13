<x-app-layout>
    @php
        $first = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
        $doneCount = collect($gettingStarted)->where('done', true)->count();
    @endphp

    <section class="ix-gate" aria-label="Your company">
        <div class="ix-gate__inner">
            <div class="ix-gate__who">
                <h1>{{ $team->name ?? 'Your workspace' }}</h1>
                <p>Welcome back, {{ $first }}.
                    @if ($role) You are {{ \Illuminate\Support\Str::lower($role) === 'owner' ? 'the owner' : 'an '.\Illuminate\Support\Str::lower($role) }}.@endif
                    {{ $memberCount }} {{ \Illuminate\Support\Str::plural('person', $memberCount) }} here.</p>
            </div>
        </div>
    </section>

    <div class="ix-home">
        @if (request()->boolean('verified'))
            <p class="ix-alert" role="status">Your email address is confirmed. You are in.</p>
        @endif
        @if (session('getting-started-status'))
            <p class="ix-alert" role="status">{{ session('getting-started-status') }}</p>
        @endif

        {{-- First steps (R-49): shown while something is left to do and the person has not put it away. Every row is
             read from the database when the page is drawn (App\Support\GettingStarted); nothing here is a saved tick. --}}
        @if ($gettingStarted !== [])
            <section class="ix-start" aria-labelledby="start-h">
                <div class="ix-start__head">
                    <div>
                        <h2 id="start-h">Getting started</h2>
                        <p>{{ $doneCount }} of {{ count($gettingStarted) }} done. Each line opens the page where it is done.</p>
                    </div>
                    <form method="POST" action="{{ route('getting-started.dismiss') }}">
                        @csrf
                        <button type="submit" class="ix-btn ix-btn--ghost ix-btn--sm">Put this away</button>
                    </form>
                </div>
                <ol class="ix-start__steps">
                    @foreach ($gettingStarted as $i => $step)
                        <li @class(['is-done' => $step['done']]) data-step="{{ $step['key'] }}">
                            <span class="ix-start__no" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="ix-start__what"><strong>{{ $step['title'] }}</strong><span>{{ $step['note'] }}</span></span>
                            @if ($step['done'])
                                <span class="ix-start__done">Done</span>
                            @else
                                <a href="{{ $step['url'] }}" class="ix-btn ix-btn--sm">{{ $step['action'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <div class="ix-fields">
            <section class="ix-fieldblock" aria-labelledby="needs">
                <div class="ix-fieldblock__head">
                    <span class="ix-stencil-num ix-fieldblock__num">{{ str_pad((string) $unsolvedTotal, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 id="needs" class="ix-fieldblock__lbl">{{ $unsolvedTotal === 1 ? 'question is' : 'questions are' }} waiting for an answer</h2>
                </div>
                @if ($unsolved->isEmpty())
                    <p class="ix-fieldblock__none">Everything has been answered.</p>
                @else
                    <ul>
                        @foreach ($unsolved as $q)
                            <li>
                                <a href="{{ route('questions.view', ['qid' => $q->id]) }}">
                                    <span class="ix-ref">{{ \App\Support\Reference::for($q) }}</span>
                                    <span class="t">{{ $q->question }}</span>
                                    <span class="w">{{ $q->created_at->diffForHumans() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="ix-fieldblock__foot">
                    <a href="{{ route('questions') }}" class="ix-fieldblock__more">Open Knowledge</a>
                    <span class="ix-logotile"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/brand/logo_white.png') }}" alt="InfoDot" width="96" height="40"></span>
                </div>
            </section>

            <div class="ix-doors">
                <livewire:platform-link platform="files" label="Files" icon="folder" variant="door" />
                <livewire:platform-link platform="docs" label="Documents" icon="description" variant="door" />
            </div>
        </div>

        <div class="ix-split" style="grid-template-columns:minmax(0,7fr) minmax(0,5fr);margin-top:28px">
            <section aria-labelledby="sols">
                <div class="ix-pagehead" style="margin-bottom:12px">
                    <h2 id="sols" class="ix-h1" style="font-size:16px">Recent solutions</h2>
                    <a href="{{ route('solutions') }}" class="ix-btn ix-btn--sm ix-btn--ghost">All solutions</a>
                </div>
                @if ($solutions->isEmpty())
                    <div class="ix-empty ix-empty--slim"><p>No solutions yet. A solution is a procedure written as steps, so a problem is only solved once.</p><a href="{{ route('add') }}" class="ix-btn">Write the first solution</a></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table">
                            <thead><tr><th>Reference</th><th>Solution</th><th class="num">Steps</th></tr></thead>
                            <tbody>
                            @foreach ($solutions as $s)
                                <tr>
                                    <td><span class="ix-ref">{{ \App\Support\Reference::for($s) }}</span></td>
                                    <td><a class="title" href="{{ route('solutions.view', ['id' => $s->id]) }}">{{ $s->solution_title }}</a></td>
                                    <td class="num">{{ $s->steps ?? 0 }}<span class="ix-sr"> {{ \Illuminate\Support\Str::plural('step', (int) ($s->steps ?? 0)) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section aria-labelledby="crew">
                <div class="ix-pagehead" style="margin-bottom:12px">
                    <h2 id="crew" class="ix-h1" style="font-size:16px">People</h2>
                    <a href="{{ route('people') }}" class="ix-btn ix-btn--sm ix-btn--ghost">Everyone</a>
                </div>
                <div class="ix-panel">
                    <div class="ix-roster">
                        @foreach ($people as $person)
                            <a href="{{ route('profile.show', ['id' => $person->id]) }}">
                                <x-ix.avatar :user="$person" />
                                <span>{{ $person->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

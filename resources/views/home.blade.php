<x-app-layout>
    @php($first = \Illuminate\Support\Str::before(auth()->user()->name, ' '))

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
                    <span class="ix-logotile"><img src="{{ asset('img/logo.png') }}" alt="InfoDot" width="96" height="40"></span>
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
                    <div class="ix-empty" style="padding:28px 24px"><p style="margin:0">No solutions yet. Write the first one when you work something out.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table">
                            <thead><tr><th>Reference</th><th>Solution</th><th class="num">Steps</th></tr></thead>
                            <tbody>
                            @foreach ($solutions as $s)
                                <tr>
                                    <td><span class="ix-ref">{{ \App\Support\Reference::for($s) }}</span></td>
                                    <td><a class="title" href="{{ route('solutions.view', ['id' => $s->id]) }}">{{ $s->solution_title }}</a></td>
                                    <td class="num">{{ $s->steps ?? 0 }}</td>
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
                                <span class="ix-avatar">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                                <span>{{ $person->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

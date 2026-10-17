<x-app-layout>
    @php($first = \Illuminate\Support\Str::before(auth()->user()->name, ' '))

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Welcome back, {{ $first }}</h1>
            <p class="ix-lede">{{ $team->name ?? 'Your workspace' }}@if($role) &middot; you are {{ \Illuminate\Support\Str::lower($role) === 'owner' ? 'the owner' : 'an '.\Illuminate\Support\Str::lower($role) }}@endif</p>
        </div>
    </div>

    <div class="ix-split" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
        <div class="ix-stack">
            <section aria-labelledby="needs">
                <div class="ix-pagehead" style="margin-bottom:12px">
                    <h2 id="needs" class="ix-h1" style="font-size:16px">Waiting for an answer
                        @if ($unsolvedTotal > 0)<span class="ix-chip ix-chip--open" style="margin-left:8px">{{ $unsolvedTotal }}</span>@endif
                    </h2>
                    <a href="{{ route('questions') }}" class="ix-btn ix-btn--sm ix-btn--ghost">All questions</a>
                </div>
                @if ($unsolved->isEmpty())
                    <div class="ix-empty" style="padding:32px 24px"><p style="margin:0">Nothing is waiting. Every question has been answered.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table">
                            <thead><tr><th>Question</th><th>Asked by</th><th class="num">Replies</th><th>When</th></tr></thead>
                            <tbody>
                            @foreach ($unsolved as $q)
                                <tr>
                                    <td><a class="title" href="{{ route('questions.view', ['qid' => $q->id]) }}">{{ $q->question }}</a></td>
                                    <td class="muted">{{ $q->user->name ?? 'Former member' }}</td>
                                    <td class="num">{{ $q->comments_count }}</td>
                                    <td class="muted">{{ $q->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section aria-labelledby="sols">
                <div class="ix-pagehead" style="margin-bottom:12px">
                    <h2 id="sols" class="ix-h1" style="font-size:16px">Recent solutions</h2>
                    <a href="{{ route('solutions') }}" class="ix-btn ix-btn--sm ix-btn--ghost">All solutions</a>
                </div>
                @if ($solutions->isEmpty())
                    <div class="ix-empty" style="padding:32px 24px"><p style="margin:0">No solutions yet. Write the first one when you work something out.</p></div>
                @else
                    <div class="ix-tablewrap">
                        <table class="ix-table">
                            <thead><tr><th>Solution</th><th>Author</th><th class="num">Steps</th><th>When</th></tr></thead>
                            <tbody>
                            @foreach ($solutions as $s)
                                <tr>
                                    <td><a class="title" href="{{ route('solutions.view', ['id' => $s->id]) }}">{{ $s->solution_title }}</a></td>
                                    <td class="muted">{{ $s->user->name ?? 'Former member' }}</td>
                                    <td class="num">{{ $s->steps ?? 0 }}</td>
                                    <td class="muted">{{ $s->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="ix-stack" aria-label="Your workspace">
            <section class="ix-panel">
                <h2 class="ix-panel__head">Open your work</h2>
                <div style="padding:8px">
                    <livewire:platform-link platform="files" label="Files" icon="folder" />
                    <livewire:platform-link platform="docs" label="Documents" icon="description" />
                </div>
            </section>

            @if ($team)
                <section class="ix-panel">
                    <h2 class="ix-panel__head">Team</h2>
                    <div class="ix-panel__body">
                        <p style="margin:0;font-weight:600">{{ $team->name }}</p>
                        <p class="ix-hint" style="margin-top:2px"><span class="ix-fig">{{ $memberCount }}</span> {{ \Illuminate\Support\Str::plural('member', $memberCount) }}</p>
                        <p style="margin:16px 0 0"><a href="{{ route('teams.show', $team->id) }}" class="ix-btn ix-btn--sm">Team settings</a></p>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>

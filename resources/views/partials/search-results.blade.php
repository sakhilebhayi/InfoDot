{{-- The search results body, shared by the signed-in page (inside the app shell) and the public one. --}}
    @php($qTotal = $results['questions']->total())
    @php($sTotal = $results['solutions']->total())
    @php($hasWords = trim($searchTerm) !== '')

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Search</h1>
            <p class="ix-lede" role="status">
                @if ($hasWords && ($qTotal + $sTotal) > 0)
                    {{ number_format($qTotal + $sTotal) }} {{ \Illuminate\Support\Str::plural('result', $qTotal + $sTotal) }} for <strong>&ldquo;{{ $searchTerm }}&rdquo;</strong>{{ $activeTopic ? ' in '.$activeTopic->name : '' }}: {{ $qTotal }} {{ \Illuminate\Support\Str::plural('question', $qTotal) }} and {{ $sTotal }} {{ \Illuminate\Support\Str::plural('solution', $sTotal) }}.
                @elseif ($hasWords)
                    Nothing matched <strong>&ldquo;{{ $searchTerm }}&rdquo;</strong>{{ $activeTopic ? ' in '.$activeTopic->name : '' }}.
                @elseif ($activeTopic)
                    Everything filed under <strong>{{ $activeTopic->name }}</strong>: {{ $qTotal }} {{ \Illuminate\Support\Str::plural('question', $qTotal) }} and {{ $sTotal }} {{ \Illuminate\Support\Str::plural('solution', $sTotal) }}.
                @else
                    Search the questions and solutions you are allowed to see. Words match titles and descriptions. A reference (company code, Q or S, and a number) works in the search bar above.
                @endif
            </p>
        </div>
    </div>

    @include('topics._search_filter')

    @guest
        <p class="ix-hint ix-guestnote" role="note">You are seeing public questions and solutions only. <a href="{{ route('login') }}">Sign in to view</a> a result in full, and to see what your company has written.</p>
    @endguest

    @if (($qTotal + $sTotal) === 0)
        @if ($hasWords || $topicSlug !== '')
            <section class="ix-empty" aria-labelledby="search-none">
                <h2 id="search-none">No match yet</h2>
                <p>Try fewer or simpler words, check the spelling, or drop the topic. If nobody has written it down, ask it: someone who knows can reply.</p>
                <a href="{{ route('seek', array_filter(['topic' => $topicSlug ?: null, 'question' => $hasWords ? \Illuminate\Support\Str::limit(trim($searchTerm), 250, '') : null])) }}" class="ix-btn ix-btn--primary">
                    <span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask {{ $hasWords ? 'this as a question' : 'a question' }}
                </a>
                <a href="{{ route('topics') }}" class="ix-btn">Browse topics</a>
            </section>
        @else
            <section class="ix-empty" aria-labelledby="search-empty">
                <h2 id="search-empty">Nothing has been shared publicly yet</h2>
                <p>@guest Public questions and solutions will be listed here. Sign in to see and search what your company has written. @else Questions and solutions you are allowed to see will be listed here. @endguest</p>
                @guest
                    <a href="{{ route('login') }}" class="ix-btn ix-btn--primary">Sign in</a>
                @else
                    <a href="{{ route('seek') }}" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask a question</a>
                @endguest
            </section>
        @endif
    @else
        @if ($qTotal > 0)
            <section class="mb-8" aria-labelledby="results-questions">
                <h2 id="results-questions" class="ix-topich2">Questions <span class="ix-notices__n">{{ $qTotal }}</span></h2>
                <ul class="ix-qlist">
                    @foreach ($results['questions'] as $question)
                        @php($isSolved = (int) $question->status === 1)
                        @php($replies = $question->comments_count + $question->answers_count)
                        <li class="ix-q {{ $isSolved ? 'is-solved' : '' }}">
                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__count" aria-label="{{ $replies }} {{ \Illuminate\Support\Str::plural('reply', $replies) }}">
                                <span class="ix-stencil-num">{{ str_pad((string) min($replies, 99), 2, '0', STR_PAD_LEFT) }}</span>
                                <small>{{ \Illuminate\Support\Str::plural('reply', $replies) }}</small>
                            </a>
                            <div class="ix-q__body">
                                <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="ix-q__title">{{ $question->question }}</a>
                                <p class="ix-q__meta">
                                    <span class="ix-ref">{{ \App\Support\Reference::for($question) }}</span>
                                    <span>{{ $question->user->name ?? 'Former member' }}</span>
                                    <span>{{ $question->created_at->diffForHumans() }}</span>
                                </p>
                                @include('topics._chips', ['item' => $question])
                            </div>
                            <span class="ix-q__state {{ $isSolved ? 'is-done' : 'is-open' }}">{{ $isSolved ? 'Solved' : 'Waiting' }}</span>
                        </li>
                    @endforeach
                </ul>
                @include('partials.pager', ['pager' => $results['questions'], 'label' => 'Questions'])
            </section>
        @endif

        @if ($sTotal > 0)
            <section aria-labelledby="results-solutions">
                <h2 id="results-solutions" class="ix-topich2">Solutions <span class="ix-notices__n">{{ $sTotal }}</span></h2>
                <ul class="ix-library">
                    @foreach ($results['solutions'] as $solution)
                        <li class="ix-sol">
                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="ix-sol__link">
                                <span class="ix-sol__steps">
                                    <span class="ix-stencil-num">{{ str_pad((string) min((int) $solution->steps, 99), 2, '0', STR_PAD_LEFT) }}</span>
                                    <small>{{ \Illuminate\Support\Str::plural('step', (int) $solution->steps) }}</small>
                                </span>
                                <span class="ix-sol__text">
                                    <span class="ix-sol__title">{{ $solution->solution_title }}</span>
                                    <span class="ix-sol__ex">{{ \Illuminate\Support\Str::limit($solution->solution_description, 150) }}</span>
                                </span>
                            </a>
                            <p class="ix-sol__meta">
                                <span class="ix-ref">{{ \App\Support\Reference::for($solution) }}</span>
                                @if ($solution->isVerified())<span class="ix-chip ix-chip--done">Verified by moderator</span>@endif
                                <span>{{ $solution->user->name ?? 'Former member' }}</span>
                                <span>{{ $solution->created_at->diffForHumans() }}</span>
                            </p>
                            @include('topics._chips', ['item' => $solution])
                        </li>
                    @endforeach
                </ul>
                @include('partials.pager', ['pager' => $results['solutions'], 'label' => 'Solutions'])
            </section>
        @endif
    @endif

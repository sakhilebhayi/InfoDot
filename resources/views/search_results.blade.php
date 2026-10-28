@guest
    <x-guest-layout>
        <div class="absolute top-0 w-full h-20 bg-center bg-cover" style='background-image: url("{{ asset('/img/background.jpg') }}");'>
            <nav class="top-0 absolute z-50 w-full items-center px-2 py-3 navbar-expand-lg">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/questions') }}" aria-label="Questions" class="text-sm font-bold leading-relaxed inline-block mr-4 py-2 whitespace-no-wrap uppercase text-white">

                        </a>
                    @else
                        <div class="mx-auto justify-center grid grid-cols-3 gap-4 ">
                            <div>
                                <button aria-label="Toggle navigation menu" class="cursor-pointer text-xl leading-none px-3 py-1 border border-solid border-transparent rounded bg-transparent block lg:hidden outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                type="button"
                                onclick="toggleNavbar('example-collapse-navbar')">
                                    <i class="text-white fas fa-bars"></i>
                                </button>
                            </div>
                            <div class="lg:flex lg:h-16 w-full justify-start bg-white lg:bg-transparent lg:shadow-none hidden">
                                <livewire:search />
                            </div>
                            <div class="lg:flex w-full justify-start items-center bg-white lg:bg-transparent lg:shadow-none hidden">
                                <ul class="flex flex-col lg:flex-row list-none lg:mx-auto">
                                    <li class="flex items-center">
                                        <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://web.facebook.com/infodotbusinesses" target="_blank" >
                                                <i class="lg:text-gray-300 text-gray-500 fab fa-facebook text-lg leading-lg"></i>
                                                <span class="lg:hidden inline-block ml-2">Share</span>
                                            </a>
                                    </li>
                                    <li class="flex items-center">
                                        <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://twitter.com/InfoDot3" target="_blank" >
                                                <i class="lg:text-gray-300 text-gray-500 fab fa-twitter text-lg leading-lg"></i>
                                                <span class="lg:hidden inline-block ml-2">Tweet</span>
                                            </a>
                                    </li>
                                    <li class="flex items-center">
                                        <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://www.instagram.com/infodot_businesses_official/" target="_blank" >
                                                <i class="lg:text-gray-300 text-gray-500 fab fa-instagram text-lg leading-lg"></i>
                                                <span class="lg:hidden inline-block ml-2">Follow</span>
                                            </a>
                                    </li>
                                    <li class="flex items-center">
                                        <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://www.linkedin.com/company/infodot/" target="_blank">
                                                <i class="lg:text-gray-300 text-gray-500 fab fa-linkedin-in text-lg leading-lg"></i>
                                                <span class="lg:hidden inline-block ml-2">Connect</span>
                                            </a>
                                    </li>
                                    <li class="flex items-center">
                                        @if (Route::has('login'))
                                            <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign in
                                                </a>
                                        @endif
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign Up
                                                </a>
                                        @endif
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @endauth
                @endif
            </nav>
        </div>
        <div class="container px-4 items-center justify-between mt-24">
            @include('topics._search_filter')
            <div class="mx-2 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4">
                @if(!empty($results['questions']))
                    @foreach($results['questions'] as $question)
                        <div aria-label="group of cards" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 rounded-sm py-8 w-full">
                            <div tabindex="0" aria-label="card 1" class="mb-7 bg-white p-6 shadow rounded">
                                <div class="flex items-center border-b border-gray-200 pb-4 mb-2">
                                    {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                    <div class="flex items-start justify-between w-full">
                                        <div class="pl-3 w-full">
                                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 rounded-sm text-xl font-medium leading-5 text-gray-800">
                                                {{ $question->question }}
                                            </a>
                                            <p tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-offset-2 rounded-sm text-sm leading-normal pt-2 text-gray-500">
                                                {{ $question->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        <div role="img" aria-label="bookmark">
                                            <i class="fa fa-check-circle {{ $question->status == 0 ? 'text-red-500' : 'text-green-500' }} mt-1 mx-1" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-2">
                                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-600 focus-visible:ring-offset-2 rounded-sm text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                        {{ $question->question }}
                                    </a>

                                </div>
                                <div class="flex items-center justify-center border-t border-gray-200 pt-4 mt-3">
                                    <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
                                        Sign in to view
                                        </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if(!empty($results['solutions']))
                    @foreach($results['solutions'] as $solution)
                        <div aria-label="group of cards" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 rounded-sm py-8 w-full">
                            <div tabindex="0" aria-label="card 1" class="mb-7 bg-white p-6 shadow rounded">
                                <div class="flex items-center border-b border-gray-200 pb-4 mb-2">
                                    {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                    <div class="flex items-start justify-between w-full">
                                        <div class="pl-3 w-full">
                                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 rounded-sm text-xl font-medium leading-5 text-gray-800">
                                                {{ $solution->solution_title }}
                                            </a>
                                            <p tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-offset-2 rounded-sm text-sm leading-normal pt-2 text-gray-500">
                                                {{ $solution->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        {{-- <div role="img" aria-label="bookmark">
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        </div> --}}
                                    </div>
                                </div>
                                <div class="px-2">
                                    <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-600 focus-visible:ring-offset-2 rounded-sm text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                        {{ $solution->solution_description }}
                                    </a>
                                    @if(!empty($solution->tags))
                                        <div tabindex="0" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 rounded-sm flex">
                                            @php
                                                $tags = explode(',', $solution->tags) ?? '';
                                            @endphp
                                            @foreach($tags as $tag)
                                                <div class="py-2 mx-1 my-3 px-4 text-xs leading-3 text-indigo-700 rounded-full bg-indigo-100">
                                                    {{ $tag }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center justify-center border-t border-gray-200 pt-4 mt-3">
                                    <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-800 focus-visible:ring-offset-2 lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
                                        Sign in to view
                                        </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
        @include('layouts.footer')
    </x-guest-layout>
@else
    <x-app-layout>
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

        @if (($qTotal + $sTotal) === 0 && ($hasWords || $topicSlug !== ''))
            <section class="ix-empty" aria-labelledby="search-none">
                <h2 id="search-none">No match yet</h2>
                <p>Try fewer or simpler words, check the spelling, or drop the topic. If nobody has written it down, ask it: someone who knows can reply.</p>
                <a href="{{ route('seek', array_filter(['topic' => $topicSlug ?: null, 'question' => $hasWords ? \Illuminate\Support\Str::limit(trim($searchTerm), 250, '') : null])) }}" class="ix-btn ix-btn--primary">
                    <span class="material-symbols-rounded" aria-hidden="true">add_comment</span>Ask {{ $hasWords ? 'this as a question' : 'a question' }}
                </a>
                <a href="{{ route('topics') }}" class="ix-btn">Browse topics</a>
            </section>
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
                    {{ $results['questions']->links() }}
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
                    {{ $results['solutions']->links() }}
                </section>
            @endif
        @endif
    </x-app-layout>
@endguest

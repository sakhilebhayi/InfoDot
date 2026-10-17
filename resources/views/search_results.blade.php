@guest
    <x-guest-layout>
        <div class="absolute top-0 w-full h-20 bg-center bg-cover" style='background-image: url("{{ asset('/img/background.jpg') }}");'>
            <nav class="top-0 absolute z-50 w-full items-center px-2 py-3 navbar-expand-lg">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/questions') }}" class="text-sm font-bold leading-relaxed inline-block mr-4 py-2 whitespace-no-wrap uppercase text-white">

                        </a>
                    @else
                        <div class="mx-auto justify-center grid grid-cols-3 gap-4 ">
                            <div>
                                <button class="cursor-pointer text-xl leading-none px-3 py-1 border border-solid border-transparent rounded bg-transparent block lg:hidden outline-none focus:outline-none"
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
                                            <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign in
                                                </a>
                                        @endif
                                        @if (Route::has('register'))
                                            <a href="{{ route('register') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign Up
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
            <div class="mx-2 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4">
                @if(!empty($results['questions']))
                    @foreach($results['questions'] as $question)
                        <div aria-label="group of cards" tabindex="0" class="focus:outline-none py-8 w-full">
                            <div tabindex="0" aria-label="card 1" class="mb-7 bg-white p-6 shadow rounded">
                                <div class="flex items-center border-b border-gray-200 pb-4 mb-2">
                                    {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                    <div class="flex items-start justify-between w-full">
                                        <div class="pl-3 w-full">
                                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none text-xl font-medium leading-5 text-gray-800">
                                                {{ $question->question }}
                                            </a>
                                            <p tabindex="0" class="focus:outline-none text-sm leading-normal pt-2 text-gray-500">
                                                {{ $question->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        <div role="img" aria-label="bookmark">
                                            <i class="fa fa-check-circle {{ $question->status == 0 ? 'text-red-500' : 'text-green-500' }} mt-1 mx-1" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-2">
                                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                        {{ $question->question }}
                                    </a>

                                </div>
                                <div class="flex items-center justify-center border-t border-gray-200 pt-4 mt-3">
                                    <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
                                        Sign in to view
                                        </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if(!empty($results['solutions']))
                    @foreach($results['solutions'] as $solution)
                        <div aria-label="group of cards" tabindex="0" class="focus:outline-none py-8 w-full">
                            <div tabindex="0" aria-label="card 1" class="mb-7 bg-white p-6 shadow rounded">
                                <div class="flex items-center border-b border-gray-200 pb-4 mb-2">
                                    {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                    <div class="flex items-start justify-between w-full">
                                        <div class="pl-3 w-full">
                                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none text-xl font-medium leading-5 text-gray-800">
                                                {{ $solution->solution_title }}
                                            </a>
                                            <p tabindex="0" class="focus:outline-none text-sm leading-normal pt-2 text-gray-500">
                                                {{ $solution->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                        {{-- <div role="img" aria-label="bookmark">
                                            <i class="fas fa-check-circle text-green-500"></i>
                                        </div> --}}
                                    </div>
                                </div>
                                <div class="px-2">
                                    <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                        {{ $solution->solution_description }}
                                    </a>
                                    @if(!empty($solution->tags))
                                        <div tabindex="0" class="focus:outline-none flex">
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
                                    <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
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
        <x-slot name="header">
            <div class="flex flex-row justify-between w-full">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight flex justify-start items-center">
                    @if(count($results['questions']))
                        {{ __('Questions') }}
                    @else
                        {{ __('Business Solutions') }}
                    @endif
                </h2>
                <div>
                    <a href="{{ route('seek') }}" class="justify-items-end btn rounded-full">
                        <i class="fa fa-search mr-1" aria-hidden="true"></i> Seek
                    </a>
                    <a href="{{ route('add') }}" class="justify-items-end btn rounded-full">
                        <i class="fa fa-plus mr-1" aria-hidden="true"></i> Add
                    </a>
                </div>
            </div>
        </x-slot>
        <div class="flex">
            @include('partials.aside-left')

            <main class="w-full">
                <div class="container px-4 items-center justify-between">
                    <div class="mx-2 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4">
                        @if(!empty($results['questions']))
                            @foreach($results['questions'] as $question)
                                <div aria-label="group of cards" tabindex="0" class="focus:outline-none pt-8 w-full">
                                    <div tabindex="0" aria-label="card 1" class="bg-white p-6 shadow rounded">
                                        <div class="flex items-center border-b border-gray-200 pb-4">
                                            {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                            <div class="flex items-start justify-between w-full">
                                                <div class="pl-3 w-full">
                                                    <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none text-xl font-medium leading-5 text-gray-800">
                                                        {{ $question->question }}
                                                    </a>
                                                    <p tabindex="0" class="focus:outline-none text-sm leading-normal pt-2 text-gray-500">
                                                        {{ $question->created_at->diffForHumans() }}
                                                    </p>
                                                </div>
                                                <div role="img" aria-label="bookmark">
                                                    <i class="fa fa-check-circle {{ $question->status == 0 ? 'text-red-500' : 'text-green-500' }} mt-1 mx-1" aria-hidden="true"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="px-2">
                                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" tabindex="0" class="focus:outline-none text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                                {{ $question->question }}
                                            </a>

                                        </div>
                                        <div class="flex items-center justify-center border-t border-gray-200 pt-4 mt-3">
                                            <a href="{{ route('questions.view', ['qid' => $question->id]) }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
                                                View Question
                                                </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        @if(!empty($results['solutions']))
                            @foreach($results['solutions'] as $solution)
                                <div aria-label="group of cards" tabindex="0" class="focus:outline-none py-8 w-full">
                                    <div tabindex="0" aria-label="card 1" class="mb-7 bg-white p-6 shadow rounded">
                                        <div class="flex items-center border-b border-gray-200 pb-4 mb-2">
                                            {{-- <img src="https://cdn.tuk.dev/assets/components/misc/doge-coin.png" alt="coin avatar" class="w-12 h-12 rounded-full" /> --}}
                                            <div class="flex items-start justify-between w-full">
                                                <div class="pl-3 w-full">
                                                    <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none text-xl font-medium leading-5 text-gray-800">
                                                        {{ $solution->solution_title }}
                                                    </a>
                                                    <p tabindex="0" class="focus:outline-none text-sm leading-normal pt-2 text-gray-500">
                                                        {{ $solution->created_at->diffForHumans() }}
                                                    </p>
                                                </div>
                                                {{-- <div role="img" aria-label="bookmark">
                                                    <i class="fas fa-check-circle text-green-500"></i>
                                                </div> --}}
                                            </div>
                                        </div>
                                        <div class="px-2">
                                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" tabindex="0" class="focus:outline-none text-sm leading-5 py-4 text-gray-600 mt-3 pt-3 line-clamp-3">
                                                {{ $solution->solution_description }}
                                            </a>
                                            @if(!empty($solution->tags))
                                                <div tabindex="0" class="focus:outline-none flex">
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
                                            <a href="{{ route('solutions.view', ['id' => $solution->id]) }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">
                                                View Solution
                                                </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </main>

            @include('partials.aside-right')
        </div>
    </x-app-layout>
@endguest

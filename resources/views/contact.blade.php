<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#000000" />
        <link rel="shortcut icon" href="./img/icons/icon.png" />
        <link rel="apple-touch-icon" sizes="76x76" href="./img/icons/icon.png" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css" rel="stylesheet">
        <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
        @livewireStyles
        <title>InfoDot</title>
    </head>
    <body class="text-gray-800 antialiased">
        <nav class="top-0 absolute z-50 w-full flex flex-wrap items-center justify-between px-2 py-3 navbar-expand-lg">
            @if (Route::has('login'))
                <div class="container px-4 mx-auto flex flex-wrap items-center justify-between">
                    <div class="w-full relative flex justify-between lg:w-auto lg:static lg:block lg:justify-start">
                        @auth
                            <a href="{{ url('/questions') }}" class="text-sm font-bold leading-relaxed inline-block mr-4 py-2 whitespace-no-wrap uppercase text-white">
                                Home
                            </a>
                        @else
                            <button class="cursor-pointer text-xl leading-none px-3 py-1 border border-solid border-transparent rounded bg-transparent block lg:hidden outline-none focus:outline-none" type="button" onclick="toggleNavbar('example-collapse-navbar')">
                                <i class="text-white fas fa-bars"></i>
                            </button>
                    </div>
                    <div class="lg:flex flex-grow items-center bg-white lg:bg-transparent lg:shadow-none hidden" id="example-collapse-navbar">
                        <ul class="flex flex-col lg:flex-row list-none lg:ml-auto">
                            <li class="flex items-center">
                                <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://web.facebook.com/infodotbusinesses" target="_blank">
                                    <i class="lg:text-gray-300 text-gray-500 fab fa-facebook text-lg leading-lg"></i>
                                    <span class="lg:hidden inline-block ml-2">Share</span>
                                </a>
                            </li>
                            <li class="flex items-center">
                                <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://twitter.com/InfoDot3" target="_blank">
                                    <i class="lg:text-gray-300 text-gray-500 fab fa-twitter text-lg leading-lg "></i>
                                    <span class="lg:hidden inline-block ml-2">Tweet</span>
                                </a>
                            </li>
                            <li class="flex items-center">
                                <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://www.instagram.com/infodot_businesses_official/" target="_blank">
                                    <i class="lg:text-gray-300 text-gray-500 fab fa-instagram text-lg leading-lg"></i>
                                    <span class="lg:hidden inline-block ml-2">Follow</span>
                                </a>
                            </li>
                            <li class="flex items-center">
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign in</a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign Up</a>
                                @endif
                                @endauth
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
        </nav>
    <main>
        <div class="relative pt-16 pb-32 flex content-center items-center justify-center" style="max-height: 300px;">
            <div class="absolute top-0 w-full h-full bg-center bg-cover" style='background-image: url("./img/background.jpg");' >
          <span
            id="blackOverlay"
            class="w-full h-full absolute opacity-50 bg-black"
          ></span>
            </div>

            <div class="top-auto bottom-0 left-0 right-0 w-full absolute pointer-events-none overflow-hidden" style="height: 70px; transform: translateZ(0px);">
                <svg class="absolute bottom-0 overflow-hidden" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" version="1.1" viewBox="0 0 2560 100" x="0" y="0">
                    <polygon class="text-gray-300 fill-current" points="2560 0 2560 100 0 100"></polygon>
                </svg>
            </div>
        </div>
        <section class="relative block py-24 lg:pt-0 bg-gray-900">
            <div class="container mx-auto px-4 mt-32">
                <div class="flex flex-wrap justify-center lg:-mt-64 -mt-48">
                    <div class="w-full lg:w-6/12 px-4">
                        <h4 class="text-2xl font-semibold  text-gray-300 text-center mt-16">Want to work with us?</h4>
                        <p class="leading-relaxed mt-1 mb-4 text-gray-600 text-center">
                            Complete this form and we will get back to you in 24 hours.
                        </p>
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success w-full bg-green-500 p-5">
                                <p class="lead text-green-800 text-center">Message Sent!</p>
                            </div>
                        @endif
                        <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-gray-300 mt-16">
                            <div class="flex-auto p-5 lg:p-10">
                                <h4 class="text-2xl font-semibold text-grey-300">Contact Us</h4>
                                <form action="{{ route('send-contact') }}" method="POST">
                                    @csrf
                                    <div class="relative w-full mb-3 mt-8">
                                        <label class="block uppercase text-gray-700 text-xs font-bold mb-2" for="full-name">
                                            {{ __('Full Name') }}
                                        </label>
                                        <input aria-label="Full Name" type="text" class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:shadow-outline w-full @error('name') is-invalid @enderror" placeholder="Full Name" style="transition: all 0.15s ease 0s;" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus/>

                                        @error('name')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                    <div class="relative w-full mb-3">
                                        <label class="block uppercase text-gray-700 text-xs font-bold mb-2" for="email">
                                            {{ __('Email Address') }}
                                        </label>
                                        <input aria-label="Email" type="email" class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:shadow-outline w-full @error('email') is-invalid @enderror" placeholder="Email" style="transition: all 0.15s ease 0s;" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus/>
                                        @error('email')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                    <div class="relative w-full mb-3">
                                        <label class="block uppercase text-gray-700 text-xs font-bold mb-2" for="message">
                                            {{ __('Message') }}
                                        </label>
                                        <textarea aria-label="Type a message" rows="4" name="message" cols="80" class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:shadow-outline w-full @error('message') is-invalid @enderror" placeholder="Type a message..."></textarea>
                                        @error('message')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                    <div class="text-center mt-6">
                                        <button class="bg-gray-900 text-white active:bg-gray-700 text-sm font-bold uppercase px-6 py-3 rounded shadow hover:shadow-lg outline-none focus:outline-none mr-1 mb-1" type="submit" style="transition: all 0.15s ease 0s;">
                                            Send Message
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    @include('layouts.footer')
  </body>
</html>

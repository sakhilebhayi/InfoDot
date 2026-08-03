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
        <style>
            .input-group button {
                background-color: Transparent !important;
                border-top: 1px solid #E3E3E3;
                border-bottom: 1px solid #E3E3E3;
                border-right: 1px solid #E3E3E3;
                border-top-right-radius: 30px !important;
                border-bottom-right-radius: 30px !important;
                border-left: 0px;
                height: 50px;
            }
            .input-group button:focus {
                outline: none;
            }

            .input-group input {
                height: 50px !important;
                align-self: center;
                border-left: 1px solid #E3E3E3 !important;
                border-right: 1px solid #E3E3E3 !important;
                border-top: 1px solid #E3E3E3 !important;
                border-bottom: 1px solid #E3E3E3 !important;
                border-top-left-radius: 30px !important;
                border-bottom-left-radius: 30px !important;
                border-top-right-radius: 30px !important;
                border-bottom-right-radius: 30px !important;
                background-color: Transparent !important;
                outline: transparent;
                color: #fff;
                min-width: 80% !important;
                max-width: 85% !important;
            }
            .input-group input:focus {
                border-right: 1px solid #f96332 !important;
                border-left: 1px solid #f96332 !important;
                border-top: 1px solid #f96332 !important;
                border-bottom: 1px solid #f96332 !important;
            }
        </style>
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
                                <i class="lg:text-gray-300 text-gray-500 fab fa-twitter text-lg leading-lg"></i>
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
                            <a class="lg:text-white lg:hover:text-gray-300 text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs uppercase font-bold" href="https://www.linkedin.com/company/infodot/"target="_blank">
                                <i class="lg:text-gray-300 text-gray-500 fab fa-linkedin-in text-lg leading-lg"></i>
                                <span class="lg:hidden inline-block ml-2">Connect</span>
                            </a>
                        </li>
                        <li class="flex items-center">
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign in
                                </a>
                            @endif
                            @endauth
                        </li>
                        {{-- <li class="flex items-center">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="bg-white text-gray-800 active:bg-gray-100 text-xs font-bold uppercase px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none lg:mr-1 lg:mb-0 ml-3 mb-3" type="button" style="transition: all 0.15s ease 0s;">Sign Up
                                </a>
                            @endif
                        </li> --}}
                    </ul>
                </div>
            </div>
        @endif
    </nav>
<main>
    <div class="relative pt-16 pb-32 flex content-center items-center justify-center" style="min-height: 95vh;">
        <!-- Photographic Background: real network-cable/infrastructure photo by Taylor Vick (@tvick), unsplash.com/photos/cable-network-M5tzZtFCOfs -->
        <div class="absolute top-0 w-full h-full bg-center bg-cover" style='background-image: url("https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2400&auto=format&fit=crop");'>
            <span id="blackOverlay" class="w-full h-full absolute opacity-50 bg-black"></span>
        </div>
        <div class="container relative mx-auto">
            <div class="items-center flex flex-wrap">
                <div class="w-full lg:w-6/12 px-4 ml-auto mr-auto text-center">
                    <div class="ml-auto mr-auto text-center">
                        <div class="flex flex-wrap justify-center">
                            <img src="./img/logo_white.png" alt="logo" class="m-auto pl-5 mb-5 w-full lg:w-8/12">
                        </div>
                        <livewire:search/>
                    </div>
                </div>
            </div>
        </div>
        <div class="top-auto bottom-0 left-0 right-0 w-full absolute pointer-events-none overflow-hidden" style="height: 70px; transform: translateZ(0px);">
            <svg class="absolute bottom-0 overflow-hidden" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" version="1.1" viewBox="0 0 2560 100" x="0" y="0">
                <polygon class="text-gray-300 fill-current" points="2560 0 2560 100 0 100"></polygon>
            </svg>
        </div>
    </div>
    <section class="pb-20 bg-gray-300 -mt-24 z-0">
        <div class="container mx-auto px-4 z-0">
            <div class="flex flex-wrap z-0">
                <div class="lg:pt-12 pt-6 w-full md:w-4/12 px-4 text-center z-0">
                    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-8 shadow-lg rounded-lg">
                        <div class="px-4 py-5 flex-auto">
                            <div class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 mb-5 shadow-lg rounded-full bg-red-400">
                                <i class="fas fa-award"></i>
                            </div>
                            <h6 class="text-xl font-semibold">Reliable Content & Resources</h6>
                            <p class="mt-2 mb-4 text-gray-600">
                                Get insite about business strategies from our curated content and resources provided by our Industry Leading partners.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-4/12 px-4 text-center z-0">
                    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-8 shadow-lg rounded-lg">
                        <div class="px-4 py-5 flex-auto">
                            <div class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 mb-5 shadow-lg rounded-full bg-blue-400">
                                <i class="fas fa-retweet"></i>
                            </div>
                            <h6 class="text-xl font-semibold">Value For Value</h6>
                            <p class="mt-2 mb-4 text-gray-600">
                                Find endorsed service providers or partnerships, and get found by potential partners or customers.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="pt-6 w-full md:w-4/12 px-4 text-center z-0">
                    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-8 shadow-lg rounded-lg">
                        <div class="px-4 py-5 flex-auto">
                            <div class="text-white p-3 text-center inline-flex items-center justify-center w-12 h-12 mb-5 shadow-lg rounded-full bg-green-400">
                                <i class="fas fa-fingerprint"></i>
                            </div>
                            <h6 class="text-xl font-semibold">Verified Partners</h6>
                            <p class="mt-2 mb-4 text-gray-600">
                                Increase your visibility & credibility by sharing or providing winning strategies to struggling entrepreneurs.
                            </p>
                        </div>
                    </div>
                </div>
          </div>
          <div class="flex flex-wrap items-center mt-32 z-0">
                <div class="w-full md:w-5/12 px-4 mr-auto ml-auto">
                    <div class="text-blue-600 p-3 text-center inline-flex items-center justify-center w-16 h-16 mb-6 shadow-lg rounded-full bg-gray-100">
                        <i class="fas fa-user-friends text-xl"></i>
                    </div>
                    <h3 class="text-3xl mb-2 font-semibold leading-normal">We love Entrepreneurship....</h3>
                    <p class="text-lg font-light leading-relaxed mt-4 mb-4 text-gray-700">
                        InfoDot is an open community. It was built to empower entrepreneurs &amp; people who are in business. We connect them to strategies, solutions &amp; and service providers that enable problem solving, productivity, growth, and discovery.
                    </p>
                    <p class="text-lg font-light leading-relaxed mt-0 mb-4 text-gray-700">
                        We help you get answers to your toughest business questions, By providing &amp; organizing the various approaches to business &amp; making them universally accessible to you.
                    </p>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="font-bold text-gray-800 mt-8">Get Started</a>
                    @endif
                </div>
                <div class="w-full md:w-4/12 px-4 mr-auto ml-auto">
                    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded-lg bg-blue-600">
                        <img alt="infodot" src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=crop&amp;w=1051&amp;q=80" class="w-full align-middle rounded-t-lg"/>
                        <blockquote class="relative p-8 mb-4">
                            <svg preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 583 95" class="absolute left-0 w-full block" style="height: 95px; top: -94px;">
                                <polygon points="-30,95 583,95 583,65" class="text-blue-600 fill-current"></polygon>
                            </svg>
                            <h4 class="text-xl font-bold text-white">See How Much Trouble We Could Save You.</h4>
                            <p class="text-md font-light mt-2 text-white">
                                We can help you scale even to a global level while enjoying an environment with a unique entrepreneurial spirit.
                            </p>
                        </blockquote>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="relative py-20">
        <div class="bottom-auto top-0 left-0 right-0 w-full absolute pointer-events-none overflow-hidden -mt-20" style="height: 80px; transform: translateZ(0px);">
            <svg class="absolute bottom-0 overflow-hidden" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" version="1.1" viewBox="0 0 2560 100" x="0" y="0">
                <polygon class="text-white fill-current" points="2560 0 2560 100 0 100"></polygon>
            </svg>
        </div>
        <div class="container mx-auto px-4">
            <div class="items-center flex flex-wrap">
                <div class="w-full md:w-4/12 ml-auto mr-auto px-4">
                    <img alt="infodot" class="max-w-full rounded-lg shadow-lg" src="https://images.unsplash.com/photo-1555212697-194d092e3b8f?ixlib=rb-1.2.1&amp;ixid=eyJhcHBfaWQiOjEyMDd9&amp;auto=format&amp;fit=crop&amp;w=634&amp;q=80"/>
                </div>
                <div class="w-full md:w-5/12 ml-auto mr-auto px-4">
                    <div class="md:pr-12">
                        <div class="text-blue-600 p-3 text-center inline-flex items-center justify-center w-16 h-16 mb-6 shadow-lg rounded-full bg-blue-300">
                            <i class="fas fa-rocket text-xl"></i>
                        </div>
                        <h3 class="text-3xl font-semibold">
                            Building Not Just A Community, But A Way To Successful Businesses.
                        </h3>
                        <p class="mt-4 text-lg leading-relaxed text-gray-600">
                            Join Thousands Of Entrepreneurs Improving Their Businesses With Us Everyday. Build confidence in knowing that you are not alone and meet new associates who can help you grow your business.
                        </p>
                        <ul class="list-none mt-6">
                            <li class="py-2">
                                <div class="flex items-center">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200 mr-3">
                                            <i class="fas fa-fingerprint"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="text-gray-600">Build Useful &amp; Beneficial Networks,</h4>
                                    </div>
                                </div>
                            </li>
                            <li class="py-2">
                                <div class="flex items-center">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200 mr-3">
                                            <i class="fas fa-retweet"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="text-gray-600">Exchange A Service For A Service,</h4>
                                    </div>
                                </div>
                            </li>
                            <li class="py-2">
                                <div class="flex items-center">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200 mr-3">
                                            <i class="far fa-paper-plane"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="text-gray-600">Learn, Share Knowledge &amp; Grow Your Business.</h4>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="pb-20 relative block bg-gray-900">
        <div class="bottom-auto top-0 left-0 right-0 w-full absolute pointer-events-none overflow-hidden -mt-20" style="height: 80px; transform: translateZ(0px);">
            <svg class="absolute bottom-0 overflow-hidden" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"
            version="1.1" viewBox="0 0 2560 100" x="0" y="0">
                <polygon class="text-gray-900 fill-current" points="2560 0 2560 100 0 100"></polygon>
            </svg>
        </div>
        <div class="container mx-auto px-4 lg:pt-24 lg:pb-24">
            <div class="flex flex-wrap text-center justify-center">
                <div class="w-full lg:w-6/12 px-4">
                    <h2 class="text-4xl font-semibold text-white">Business Development Tools for Business Intelligence</h2>
                    <p class="text-lg leading-relaxed mt-4 mb-4 text-gray-500">
                        Our unique technique turns your normal learning experience into an incredible business adventure.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap mt-12 justify-center">
                <div class="w-full lg:w-3/12 px-4 text-center">
                    <div class="text-gray-900 p-3 w-12 h-12 shadow-lg rounded-full bg-white inline-flex items-center justify-center">
                        <i class="fas fa-medal text-xl"></i>
                    </div>
                    <h6 class="text-xl mt-5 font-semibold text-white">Excelent Services</h6>
                    <p class="mt-2 mb-4 text-gray-500">Built by entrepreneurs for entrepreneurs who want to develop their business.</p>
                </div>
                <div class="w-full lg:w-3/12 px-4 text-center">
                    <div class="text-gray-900 p-3 w-12 h-12 shadow-lg rounded-full bg-white inline-flex items-center justify-center">
                        <i class="fas fa-poll text-xl"></i>
                    </div>
                    <h5 class="text-xl mt-5 font-semibold text-white">Grow Your Business</h5>
                    <p class="mt-2 mb-4 text-gray-500">Find & get found by providing value-for-value exchange through the InfoDot Platform.</p>
                </div>
                <div class="w-full lg:w-3/12 px-4 text-center">
                    <div class="text-gray-900 p-3 w-12 h-12 shadow-lg rounded-full bg-white inline-flex items-center justify-center">
                        <i class="fas fa-lightbulb text-xl"></i>
                    </div>
                    <h5 class="text-xl mt-5 font-semibold text-white">Business Insights</h5>
                    <p class="mt-2 mb-4 text-gray-500">
                        Whichever industry or level your business is in, InfoDot can support you.
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>
<footer class="relative bg-gray-300 pt-8 pb-6">
    <div class="bottom-auto top-0 left-0 right-0 w-full absolute pointer-events-none overflow-hidden -mt-20" style="height: 80px; transform: translateZ(0px);">
        <svg class="absolute bottom-0 overflow-hidden" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" version="1.1" viewBox="0 0 2560 100" x="0" y="0">
            <polygon class="text-gray-300 fill-current" points="2560 0 2560 100 0 100"></polygon>
        </svg>
    </div>
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap">
            <div class="w-full lg:w-6/12 px-4">
                <h4 class="text-3xl font-semibold">Let's keep in touch!</h4>
                <h5 class="text-lg mt-0 mb-2 text-gray-700">Find us on any of these platforms.</h5>
                <div class="mt-6">
                    <a class="bg-white text-blue-600 shadow-lg font-normal p-3 items-center justify-center align-center rounded-full outline-none focus:outline-none mr-2" href="https://web.facebook.com/infodotbusinesses" target="_blank" type="button" >
                        <i class="fab fa-facebook-square"></i>
                    </a>
                    <a class="bg-white text-pink-400 shadow-lg font-normal p-3 items-center justify-center align-center rounded-full outline-none focus:outline-none mr-2" href="https://www.instagram.com/infodot_businesses_official/" target="_blank" type="button">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a class="bg-white text-blue-400 shadow-lg font-normal p-3 items-center justify-center align-center rounded-full outline-none focus:outline-none mr-2" href="https://twitter.com/InfoDot3" target="_blank" type="button">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a class="bg-white text-blue-400 shadow-lg font-normal p-3 items-center justify-center align-center rounded-full outline-none focus:outline-none mr-2" href="https://www.linkedin.com/company/infodot/" target="_blank" type="button">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>
            <div class="w-full lg:w-6/12 px-4">
                <div class="flex flex-wrap items-top mb-6">
                    <div class="w-full lg:w-4/12 px-4 ml-auto">
                        <span class="block uppercase text-gray-600 text-sm font-semibold mb-2">Useful Links</span>
                        <ul class="list-unstyled">
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm"href="https://infodot.co.za">Home</a>
                            </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('login') }}">Sign In</a>
                            </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('register') }}">Create Account</a>
                            </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="https://blupininc.com" target="_blank">BluePin Inc</a>
                            </li>
                        </ul>
                    </div>
                    <div class="w-full lg:w-4/12 px-4">
                        <span class="block uppercase text-gray-600 text-sm font-semibold mb-2">Other Resources</span>
                        <ul class="list-unstyled">
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('about') }}">About Us
                                </a>
                            </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('features') }}">Features
                                </a>
                            </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('contact') }}">Contact Us
                                </a>
                             </li>
                            <li>
                                <a class="text-gray-700 hover:text-gray-900 font-semibold block pb-2 text-sm" href="{{ route('terms') }}">Terms &amp; Conditions
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <hr class="my-6 border-gray-400" />
        <div class="flex flex-wrap items-center md:justify-between justify-center">
            <div class="w-full md:w-4/12 px-4 mx-auto text-center">
                <div class="text-sm text-gray-600 font-semibold py-1">
                    Copyright © 2019 InfoDot
                    <a href="https://infodot.co.za" class="text-gray-600 hover:text-gray-900">
                        All Rights Reserved.
                    </a>.
                </div>
            </div>
        </div>
    </div>
</footer>
@livewireScripts
</body>
</html>

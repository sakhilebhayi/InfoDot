{{-- R-69: InfoDot's half of its own split sign-in pages. A photograph taken in South Africa, InfoDot's promise and what the account
     opens, all true today. On a phone the side's parts join the split grid (layouts/guest) so the photograph sits above the
     form and the points below it. Photographs: Unsplash License, credited on the About page. --}}
@props(['kind' => 'account'])
@php
    $panels = [
        'login' => [
            'img' => 'auth-cafe', 'w' => 1400, 'h' => 933, 'pos' => '62% 40%',
            'alt' => 'A woman at a table in a Cape Town cafe, among the plants by the window',
            'title' => 'A better, more connected way to build a business.',
            'points' => [
                ['Your knowledge', "Your company's questions and solutions, and everything other businesses have shared."],
                ['Your business', 'Your business page, your team, and the messages and appointments in your inbox.'],
                ['Your tools', 'Every Dot tool your business uses, opened with this one sign-in.'],
            ],
            'foot' => null,
        ],
        'register' => [
            'img' => 'auth-start-here', 'w' => 1400, 'h' => 2100, 'pos' => '50% 100%',
            'alt' => 'A sign reading "Please start here" hanging over the counter of a Cape Town coffee shop',
            'title' => 'Your first step to a better-equipped business.',
            'points' => [
                ['Stop figuring it out alone', 'Ask questions, see what other businesses learned and follow researched solutions.'],
                ['Be easy to find', 'Give your business a page in the directory, and receive messages and appointment requests.'],
                ['Bring your team and tools together', 'Add the people who work with you, and open the Dot tools your business uses with one account.'],
            ],
            'foot' => 'Creating an account is free. You set up your company on the next step.',
        ],
        'account' => [
            'img' => 'auth-market-hall', 'w' => 1400, 'h' => 933, 'pos' => '50% 55%',
            'alt' => 'Stalls and shoppers under the roof of the Rosebank Sunday Market in Johannesburg',
            'title' => 'One account for the knowledge, tools and businesses around you.',
            'points' => [
                ['Kept for you', "Your company's questions and solutions, your team, your inbox and the Dot tools you use all sit behind this account."],
                ['Kept safe', 'Checking it is really you keeps all of that out of anyone else\'s hands.'],
            ],
            'foot' => null,
        ],
    ];
    $p = $panels[$kind] ?? $panels['account'];
    $src = \App\Support\OptionalVite::asset('resources/img/auth/'.$p['img'].'.jpg');
@endphp
{{-- On a desktop this side stays on screen while a long form scrolls; on a phone it dissolves so its parts sit around the form. --}}
<div class="ix-sa__side">
@if ($src)
    <img class="ix-sa__img" src="{{ $src }}" alt="{{ $p['alt'] }}" width="{{ $p['w'] }}" height="{{ $p['h'] }}" style="object-position: {{ $p['pos'] }}" decoding="async" fetchpriority="high">
@endif
<div class="ix-sa__scrim" aria-hidden="true"></div>
<h2 class="ix-sa__title">{{ $p['title'] }}</h2>
<dl class="ix-sa__points" aria-label="What your InfoDot account opens">
    @foreach ($p['points'] as [$term, $text])
        <div><dt>{{ $term }}</dt><dd>{{ $text }}</dd></div>
    @endforeach
</dl>
@if ($p['foot'])
    <p class="ix-sa__foot">{{ $p['foot'] }}</p>
@endif
</div>

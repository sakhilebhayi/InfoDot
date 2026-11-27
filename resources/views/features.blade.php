<x-public-layout title="What InfoDot does" reveal band="WHAT IT DOES" crumb="What InfoDot does" description="What InfoDot does today: business knowledge, connected tools, a way to reach other businesses, and somewhere to keep what you learn, with one free account.">
    <article class="ix-prose ix-prose--ledger pg-prose">
        <span class="pg-plate" data-pg-plate>What it does</span>
        <h1 class="ix-h1 pg-title" data-pg-in>What InfoDot does</h1>
        <p class="ix-lede" data-pg-in>Everything on this page works today. It is organised around the four things every business needs: knowledge, tools, connections, and a way to turn what you learn into progress. Start with whichever one you need first.</p>

        <figure class="pg-strip" data-pg-photo>
            {{-- R-75 P3: phones get the 800px image and WebP comes first; the 1600px JPEG stays the fallback. --}}
            <picture>
                <source type="image/webp" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-city-800.webp') }} 800w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-city-1600.webp') }} 1600w" sizes="(max-width: 900px) 100vw, 1232px">
                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-city-1600.jpg') }}" srcset="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-city-800.jpg') }} 800w, {{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/jhb-city-1600.jpg') }} 1600w" sizes="(max-width: 900px) 100vw, 1232px" alt="The Johannesburg city centre at sunset, seen from above" width="1600" height="1067" loading="eager" decoding="async">
            </picture>
        </figure>

        <ul class="pg-vignettes" role="list" aria-label="Three things you will see on InfoDot">
            <li data-pg-reveal>
                <span class="ix-stamp pg-vignettes__stamp" aria-hidden="true">Solved</span>
                <p><strong>The stamp.</strong> The person who asked accepts the answer that worked, and the question is marked solved.</p>
            </li>
            <li data-pg-reveal>
                <span class="ix-ref ix-ref--lg pg-vignettes__ref">NWFN-Q-000014</span>
                <p><strong>The reference.</strong> Everything a company owns carries its owner code, and every reference can be searched.</p>
            </li>
            <li data-pg-reveal>
                <span class="pg-plate pg-plate--mustard pg-vignettes__plate" style="--pg-tone: var(--ix-platforms)">One account</span>
                <p><strong>The handoff.</strong> Sign in once and open the platforms your business can use, without signing in again.</p>
            </li>
        </ul>

        <h2>Knowledge: stop figuring it out alone</h2>

        <h3>Ask and answer</h3>
        <p>Ask in plain words, and say what you tried. Anyone allowed to see the question can answer. The person who asked accepts the answer that solved it and the question is stamped SOLVED. You can comment to ask for more detail, and the author is told.</p>

        <h3>Solutions, as steps</h3>
        <p>A solution is a procedure written as numbered steps that someone can follow and tick off. A moderator who did not write it can mark it verified, so a reader knows it has been checked.</p>

        <h3>Topics, references and search</h3>
        <p>Questions and solutions are filed under topics. Each carries your company's four-letter code and a reference such as NWFN-Q-000014, and both can be searched. Search before you ask: most problems have been met before.</p>

        <h3>The verified library</h3>
        <p>Answers and solutions that members of the community vouched for and a moderator then adopted. What you read is the live page: if it changes or is hidden, the library follows.</p>

        <h3>Researched solutions</h3>
        <p>InfoDot's official account publishes researched solutions on subjects such as registration, tax, hiring and pricing, written as steps from sources that are named and dated. Where a figure changes yearly, the entry says when it was last checked.</p>

        <h2>Tools: stop juggling disconnected apps</h2>

        <h3>One account for your business tools</h3>
        <p>Sign in once and open the tools your business can use without signing in again. The handoff is single-use and expires within a minute.</p>

        <h3>Tools, by what you want to get done</h3>
        <p>The tools are grouped by goal: understanding your business, managing your money and your people, organising your work, creating and collaborating, managing information, communicating, and specialised solutions. Which ones you can open depends on your business, who you are and how access is arranged, and not every tool comes with every account. To ask what yours can open, <a href="{{ route('contact') }}">use the contact form</a>. <a href="{{ route('business-tools') }}">See the tools by goal</a>.</p>

        <h3>People and companies</h3>
        <p>An account belongs to one or more companies, with roles that decide who can invite and manage. Every company has its own workspace.</p>

        <h2>Connections: stop missing what you can't see</h2>

        <h3>Business pages and the directory</h3>
        <p>A business can set up a page with its logo, photographs and what it offers, and choose who may see it. Businesses that choose to be public can be found in the <a href="{{ route('businesses.index') }}">business directory</a>, by what they do and where they work.</p>

        <h3>The inbox</h3>
        <p>Message a business from its page, as yourself or for one of your companies. The business answers as the business, from an inbox its owner and administrators share, and the conversation stays in one place.</p>

        <h3>Appointments</h3>
        <p>Where a business has published a booking calendar, you choose a time on it. Where it has not, you request an appointment with a date that suits you, and the business accepts or declines it.</p>

        <h2>Progress: turn what you learn into what you do</h2>

        <h3>Knowledge that improves</h3>
        <p>Solutions can be improved over time, and the earlier versions are kept, so the next person starts from the best answer so far.</p>

        <h3>Knowledge health</h3>
        <p>An analytics view shows what was asked, what was solved and how many replies each question gets, so a company can see where its knowledge has gaps.</p>

        <h3>Notifications</h3>
        <p>You are told when someone answers your question or comments on it, accepts your answer, verifies your solution or adds your work to the library. You can turn this off, and nothing nags you to come back.</p>

        <h2>Trust you can check</h2>

        <h3>Company first, public by choice</h3>
        <p>What your company writes is visible to your company. A question or solution becomes public only when someone chooses it, and then anyone can find it in the public search. Another company can never open yours.</p>

        <h3>Moderation you can see</h3>
        <p>Moderators can hide, verify and adopt content. Every action is recorded with a reason, authors can appeal, and moderators cannot act on their own work.</p>

        <p><a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Join InfoDot for free</a> <a href="{{ route('solution_search_results') }}" class="ix-btn">Browse public answers</a></p>

    </article>
</x-public-layout>

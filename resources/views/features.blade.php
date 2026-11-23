<x-public-layout title="What InfoDot does" reveal band="INFO" crumb="What InfoDot does" description="What InfoDot does today: discover practical business knowledge, manage your business with connected tools, and grow with what you learn.">
    <article class="ix-prose ix-prose--ledger pg-prose">
        <span class="pg-plate" data-pg-plate>Features</span>
        <h1 class="ix-h1 pg-title" data-pg-in>What InfoDot does</h1>
        <p class="ix-lede" data-pg-in>Everything on this page works today. InfoDot has three jobs: help you discover what works, manage your business with connected tools, and grow with what you learn.</p>

        <figure class="pg-strip" data-pg-photo>
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/pages/features-1600.jpg') }}" alt="A desk seen from above: a laptop with a spreadsheet, a calculator, invoices and a notebook" width="1600" height="1067" loading="eager" decoding="async">
            <figcaption>Photograph by <a href="https://unsplash.com/photos/a-person-sitting-at-a-table-with-a-laptop-oUbzU87d1Gc" rel="noopener">Microsoft 365</a> on Unsplash</figcaption>
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

        <h2>Discover: learn what works</h2>

        <h3>Ask and answer</h3>
        <p>Ask in plain words, and say what you tried. Anyone allowed to see the question can answer. The person who asked accepts the answer that solved it and the question is stamped SOLVED. You can comment to ask for more detail, and the author is told.</p>

        <h3>Solutions, as steps</h3>
        <p>A solution is a procedure written as numbered steps that someone can follow and tick off. A moderator who did not write it can mark it verified, so a reader knows it has been checked.</p>

        <h3>Topics, references and search</h3>
        <p>Questions and solutions are filed under topics. Each carries your company's four-letter code and a reference such as NWFN-Q-000014, and both can be searched. Search before you ask: most problems have been met before.</p>

        <h3>The verified library</h3>
        <p>Answers and solutions that members of the community vouched for and a moderator then adopted. What you read is the live page: if it changes or is hidden, the library follows.</p>

        <h3>Notifications</h3>
        <p>You are told when someone answers your question or comments on it, accepts your answer, verifies your solution or adds your work to the library. You can turn this off, and nothing nags you to come back.</p>

        <h2>Manage: run your business</h2>

        <h3>One account for your business tools</h3>
        <p>Sign in once and open the tools your business can use without signing in again. The handoff is single-use and expires within a minute.</p>

        <h3>Tools, by what you want to get done</h3>
        <p>The tools are grouped by goal: understanding your business, managing your money and your people, organising your work, creating and collaborating, managing information, communicating, and specialised solutions. Which ones you can open depends on your business, who you are and how access is arranged, and not every tool comes with every account. <a href="{{ route('welcome') }}#tools">See the tools by goal</a>.</p>

        <h3>People and companies</h3>
        <p>An account belongs to one or more companies, with roles that decide who can invite and manage. Every company has its own workspace.</p>

        <h2>Grow: build your future</h2>

        <h3>Knowledge health</h3>
        <p>An analytics view shows what was asked, what was solved and how many replies each question gets, so a company can see where its knowledge has gaps.</p>

        <h3>Business profiles and the directory</h3>
        <p>A business can set up a profile and choose who may see it. Businesses that choose to be public can be found in the <a href="{{ route('businesses.index') }}">business directory</a>.</p>

        <h2>Trust you can check</h2>

        <h3>Company first, public by choice</h3>
        <p>What your company writes is visible to your company. A question or solution becomes public only when someone chooses it, and then anyone can find it in the public search. Another company can never open yours.</p>

        <h3>Moderation you can see</h3>
        <p>Moderators can hide, verify and adopt content. Every action is recorded with a reason, authors can appeal, and moderators cannot act on their own work.</p>

        <p><a href="{{ route('register') }}" class="ix-btn ix-btn--primary">Join InfoDot for Free</a> <a href="{{ route('solution_search_results') }}" class="ix-btn">Browse public answers</a></p>

    </article>
</x-public-layout>

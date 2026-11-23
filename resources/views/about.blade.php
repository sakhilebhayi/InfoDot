<x-public-layout title="About" reveal band="INFO" crumb="About" description="InfoDot brings together practical business knowledge, people who share what works, and digital tools that help you start, manage and grow your business.">
    <article class="ix-prose ix-prose--ledger pg-prose">
        <span class="pg-plate" data-pg-plate>About</span>
        <h1 class="ix-h1 pg-title" data-pg-in>About InfoDot</h1>
        <p class="ix-lede" data-pg-in>InfoDot helps people start, manage and grow their businesses. It brings together practical business knowledge, people who share what works, and digital tools in one place, so nobody has to work it all out alone.</p>

        <figure class="pg-strip" data-pg-photo>
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/img/landing/couturiere.jpg') }}" alt="A seamstress smiling at her sewing machine, scissors in hand" width="1000" height="666" loading="eager" decoding="async">
            <figcaption>A business at work: the people InfoDot is for</figcaption>
        </figure>

        <h2>Why it exists</h2>
        <p>A growing business has a sales system, an accounting package, a stock sheet, a job board and a few WhatsApp groups. Each holds a slice of the truth, and what the business has learned lives in people's heads. The people who have to decide are never quite sure the figure is current, or that anyone has solved the same problem before. InfoDot is where a business, and the businesses around it, write down what they know so the next person does not start from nothing.</p>
        <p>It also puts the tools a business runs on within reach of the same account, so what you learn and what you do sit together.</p>

        <h2>What you can do here</h2>
        <ul>
            <li><strong>Discover.</strong> Find practical answers, guides and solutions, and learn from other businesses' experience. Ask your own questions and share what you know.</li>
            <li><strong>Manage.</strong> Open the digital tools that help you run your business, such as finance, people, projects, documents and files, with one account.</li>
            <li><strong>Grow.</strong> Use what you learn and the tools you connect to improve how you work and to find new opportunities.</li>
        </ul>
        <p><a href="{{ route('welcome') }}#how-it-works">See how getting started works</a>.</p>

        <h2>Who it is for</h2>
        <p>People with a business idea and no plan, people starting their first business, small businesses, growing businesses and established organisations. You do not need a company, a plan or any experience to join.</p>

        <h2>Where things stand</h2>
        <p>Today you can ask a question, answer one, write a solution as steps, have a moderator verify it, and find it again by topic or reference. One account opens the Dot platforms your business can use, grouped by what you want to get done. Which ones you can open depends on your business and how access is arranged. We describe what is there now, not what is planned.</p>

        <h2>How it is run</h2>
        <ul>
            <li><strong>Your company's work stays with your company</strong> unless someone chooses to make it public.</li>
            <li><strong>Nobody approves their own work.</strong> An answer is accepted by the person who asked; a solution is verified by a moderator who did not write it.</li>
            <li><strong>A person is in charge of anything automated.</strong> Where one of InfoDot's tools suggests something, a person decides and the record shows who.</li>
            <li><strong>No guarantees.</strong> InfoDot shares experience. It does not replace an accountant, a lawyer or an adviser, and it cannot promise that a business will succeed.</li>
        </ul>

        <h2 id="photo-credits">Photograph credits</h2>
        <p>Some photographs on the Contact, Businesses, home and What InfoDot does pages come from Unsplash and are used under the <a href="https://unsplash.com/license" rel="noopener">Unsplash licence</a>. Each was cropped and resized.</p>
        <ul>
            <li><a href="https://unsplash.com/photos/woman-using-laptop-in-workspace-HA-0i0E7sq4" rel="noopener">Woman using laptop in workspace</a> by Daniel Thomas</li>
            <li><a href="https://unsplash.com/photos/a-group-of-colorful-bags-sitting-on-top-of-a-table-9o5zeS6QbgM" rel="noopener">Rosebank Sunday Market in Johannesburg</a> by Vije Vijendranath</li>
            <li><a href="https://unsplash.com/photos/two-women-near-tables-GFrBMipOd_E" rel="noopener">Two women near tables</a> by Blake Wisz</li>
            <li><a href="https://unsplash.com/photos/a-person-sitting-at-a-table-with-a-laptop-oUbzU87d1Gc" rel="noopener">A person sitting at a table with a laptop</a> by Microsoft 365</li>
        </ul>
        <p>The other photographs, on the home, About and Questions pages, come from Wikimedia Commons. Each was cropped and resized, and each is shared under the Creative Commons Attribution-ShareAlike 4.0 licence (<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener">CC BY-SA 4.0</a>).</p>
        <ul>
            <li><a href="https://commons.wikimedia.org/wiki/File:Openspace_wao_coworking.jpg" rel="noopener">"Openspace wao coworking"</a> by WAO Coworking</li>
            <li><a href="https://commons.wikimedia.org/wiki/File:A_seamstress_at_work.jpg" rel="noopener">"A seamstress at work"</a> by Rwebogora</li>
            <li><a href="https://commons.wikimedia.org/wiki/File:Couturi%C3%A8re_3.jpg" rel="noopener">"Couturière 3"</a> by Metchyves</li>
            <li><a href="https://commons.wikimedia.org/wiki/File:Greenmarket,_Cape_Town_(P1050987).jpg" rel="noopener">"Greenmarket, Cape Town"</a> by Matti Blume</li>
        </ul>

        <h2>Get in touch</h2>
        <p>Questions, ideas or concerns: use the <a href="{{ route('contact') }}">contact form</a>. For anything about your personal information, write to <a href="mailto:privacy@infodot.co.za">privacy@infodot.co.za</a>. If something on InfoDot is wrong, see <a href="{{ route('complains') }}">how to complain</a>.</p>

    </article>
</x-public-layout>

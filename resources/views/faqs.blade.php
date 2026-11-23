@php
    // One list drives both the visible page and the FAQPage structured data (R-17 §13.3), so the
    // markup search engines read can never say something the visitor does not see.
    $faqs = [
        ['What is InfoDot?', 'A place that brings together practical business knowledge, people who share what works, and digital tools that help you start, manage and grow your business. One account opens the tools your business can use.'],
        ['What can I do with InfoDot?', 'Discover answers and solutions from other businesses, manage your business with connected tools, and grow with what you learn. Start with whichever you need today.'],
        ['Who is it for?', 'Anyone with a business idea, a new business or an established one, and the people who work for them. You do not need a company, a plan or any experience to join.'],
        ['Do I need an account?', 'You can search the public questions and solutions without one. To open a result in full, ask, answer, see your company\'s work or open other platforms, you need an account.'],
        ['Who can see my question?', 'Your company, by default. It becomes public only if someone chooses to make it public. People in other companies cannot open it.'],
        ['How do I know an answer is any good?', 'Each answer shows how it earned its place: accepted by the person who asked, or verified by a moderator who did not write it. Content a moderator has hidden is recorded with the reason.'],
        ['Does AI write the answers?', 'No. Answers come from people. Where InfoDot\'s tools use automation to suggest something, such as pointing a moderator to a good answer, a person decides and the record shows who.'],
        ['Is this legal, tax or financial advice?', 'No. It is shared experience. Check anything that affects your legal, tax or financial position with a qualified professional before you rely on it.'],
        ['Which business tools can I use?', 'The ones your business has access to, with the same account. Which tools you can open depends on your business, who you are and how access is arranged, and not every tool comes with every account. After you sign in, the Platforms page lists yours and opens each one signed in.'],
        ['How is my personal information handled?', 'Read the privacy policy and the cookie policy. For requests about your information, write to privacy@infodot.co.za.'],
        ['Something is wrong, or I need help.', 'Use the contact form. To report a question or answer, use the report link on it. To raise a complaint, see how to complain.'],
    ];

    $links = [
        'privacy policy' => route('policy'),
        'cookie policy' => route('cookies'),
        'contact form' => route('contact'),
        'how to complain' => route('complains'),
        'privacy@infodot.co.za' => 'mailto:privacy@infodot.co.za',
    ];

    $linked = function (string $text) use ($links): string {
        $html = e($text);
        foreach ($links as $label => $url) {
            $html = str_replace(e($label), '<a href="'.e($url).'">'.e($label).'</a>', $html);
        }

        return $html;
    };
@endphp
<x-public-layout title="Questions people ask" band="INFO" crumb="Questions people ask" description="Answers to common questions about InfoDot: what it is, what you can do with it, who can see your questions, how answers are trusted, and how to contact us.">
    <article class="ix-prose ix-prose--ledger">
        <h1 class="ix-h1">Questions people ask</h1>

        @foreach ($faqs as [$question, $answer])
            <h2>{{ $question }}</h2>
            <p>{!! $linked($answer) !!}</p>
        @endforeach
    </article>

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqs)->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</x-public-layout>

<x-guest-layout>
    <div class="pt-4 bg-[var(--paper)]">
        <div class="min-h-screen flex flex-col items-center pt-10 sm:pt-16 px-5 pb-16">
            <div>
                <x-jet-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-3xl mt-8 p-6 sm:p-10 bg-white border border-[var(--line)] shadow-lg overflow-hidden sm:rounded-xl prose prose-neutral max-w-none prose-headings:font-display prose-headings:text-[var(--ink)] prose-p:text-[var(--ink-soft)] prose-li:text-[var(--ink-soft)] prose-a:text-[var(--ink-soft)] hover:prose-a:text-[var(--gold-deep)] prose-strong:text-[var(--ink)]">
                <h1 class="title text-center mb-1">Privacy Policy</h1>
                {!! $policy !!}
            </div>
        </div>
    </div>
    @include('layouts.footer')
</x-guest-layout>

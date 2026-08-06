<x-guest-layout>
    <div class="pt-4 bg-[var(--paper)]">
        <div class="min-h-screen flex flex-col items-center pt-10 sm:pt-16 px-5">
            <div>
                <x-jet-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-2xl mt-8 p-6 sm:p-10 bg-white border border-[var(--line)] shadow-lg overflow-hidden sm:rounded-xl prose prose-neutral max-w-none prose-headings:font-display prose-headings:text-[var(--ink)] prose-p:text-[var(--ink-soft)] prose-a:text-[var(--ink-soft)] hover:prose-a:text-[var(--gold-deep)]">
                <h1 class="title text-center">Policy</h1>
            </div>
        </div>
    </div>
    @include('layouts.footer')
</x-guest-layout>

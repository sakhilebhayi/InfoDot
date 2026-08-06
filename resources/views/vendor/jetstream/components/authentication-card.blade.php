<div class="min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0 px-5 bg-[var(--paper)]">
    <div>
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md mt-8 px-6 sm:px-8 py-8 bg-white border border-[var(--line)] shadow-lg overflow-hidden sm:rounded-xl">
        {{ $slot }}
    </div>
</div>

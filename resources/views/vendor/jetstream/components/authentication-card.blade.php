<div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0 px-5 py-10 overflow-hidden">
    {{-- Same hero photo + overlay treatment as welcome.blade.php's hero (network/server cabling,
    Taylor Vick, @tvick, unsplash.com/photos/cable-network-M5tzZtFCOfs) — reused as-is rather than a
    second asset, so every InfoDot auth page matches the welcome page's visual identity for free. --}}
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=2400&auto=format&fit=crop');"></div>
    <div class="absolute inset-0" style="background: radial-gradient(ellipse 68% 62% at 50% 40%, rgba(36,39,43,0.88) 0%, rgba(36,39,43,0.62) 45%, rgba(36,39,43,0.3) 74%, rgba(36,39,43,0.1) 100%);"></div>
    <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(36,39,43,0.55) 0%, transparent 18%, transparent 74%, rgba(36,39,43,0.45) 100%);"></div>

    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-8 px-6 sm:px-8 py-8 bg-white border border-[var(--line)] shadow-[0_24px_60px_-24px_rgba(0,0,0,0.5)] overflow-hidden sm:rounded-xl"
         {{-- The card is always white, so the shared form components (ix- tokens) are pinned to the light set in here whatever the OS scheme is. --}}
         style="color-scheme: light; --ix-panel: #ffffff; --ix-sunken: #f6f7f8; --ix-hover: #f0f2f3; --ix-rule: #d6d9dd; --ix-rule-strong: #b3b8be; --ix-text: #14171a; --ix-text-2: #444b53; --ix-text-3: #5d656e; --ix-signal-text: #7a4f00; --ix-ok: #17724f; --ix-danger: #b3261e;">
        {{ $slot }}
    </div>
</div>

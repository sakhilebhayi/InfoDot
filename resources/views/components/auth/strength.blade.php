{{-- Password strength for registration and reset: advice, never a verdict (the server's rules decide). Pass the id of the password field. --}}
@props(['for' => 'password'])
<div class="ix-strength" x-data="{ score: 0 }"
     x-on:input.window="if ($event.target.id === '{{ $for }}') { const v = $event.target.value; score = v.length === 0 ? 0 : (v.length < 12 ? 1 : 2 + (v.length >= 16 && /[a-z]/.test(v) && /[A-Z0-9]/.test(v)) + (v.length >= 20)) }">
    <div class="ix-strength__bar" aria-hidden="true">
        <i x-bind:data-on="score >= 1"></i><i x-bind:data-on="score >= 2"></i><i x-bind:data-on="score >= 3"></i><i x-bind:data-on="score >= 4"></i>
    </div>
    <p class="ix-hint" role="status" aria-live="polite" x-text="['Use at least 12 characters. A phrase of a few words is easy to remember and hard to guess.', 'Too short: use at least 12 characters.', 'Acceptable. Longer is stronger.', 'Good.', 'Strong.'][score]">Use at least 12 characters. A phrase of a few words is easy to remember and hard to guess.</p>
</div>

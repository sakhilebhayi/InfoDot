<x-app-layout>
    <div class="ix-empty" style="max-width:640px;margin:48px auto;text-align:left">
        <h1 class="ix-h1">{{ $platform }} cannot be opened right now</h1>
        <p class="ix-lede">The item exists, but its platform is not live or not reachable from here. Try again in a moment.</p>
        <p style="margin-top:20px"><a href="{{ route('dashboard') }}" class="ix-btn">Back to Home</a></p>
    </div>
</x-app-layout>

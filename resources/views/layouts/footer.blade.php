<footer class="block pb-4">
    <hr class="mb-4 border-[var(--line)]">
    <div class="flex flex-wrap md:justify-between justify-center">
        <div class="w-full md:w-5/12 px-4 flex flex-wrap justify-center sm:justify-start sm:ml-32">
            <div class="text-sm text-[var(--ink-soft)] font-semibold py-1">
                Copyright &copy; {{ date('Y') }}
                <a href="{{ url('/') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold py-1">InfoDot</a>
            </div>
        </div>
        <div class="w-full md:w-5/12 px-4 flex flex-wrap justify-center">
            <ul class="flex flex-wrap list-none md:justify-end justify-center">
                <li>
                    <a href="{{ url('/') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">InfoDot</a>
                </li>
                <li>
                    <a href="{{ route('about') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">About Us</a>
                </li>
                <li>
                    <a href="{{ route('contact') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">Contact Us</a>
                </li>
                <li>
                    <a href="{{ route('policy') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">Privacy Policy</a>
                </li>
                <li>
                    <a href="{{ route('cookies') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">Cookie Policy</a>
                </li>
                <li>
                    <a href="{{ route('terms') }}" class="text-[var(--ink-soft)] hover:text-[var(--gold-deep)] text-sm font-semibold block py-1 px-3">Terms &amp; Conditions</a>
                </li>
            </ul>
        </div>
    </div>
</footer>

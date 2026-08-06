<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-2.5 bg-[var(--blue)] border border-transparent rounded-lg font-display font-semibold text-sm text-white hover:bg-[var(--blue-deep)] focus:bg-[var(--blue-deep)] active:bg-[var(--blue-deep)] focus:outline-none focus:ring-2 focus:ring-[var(--blue)] focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>

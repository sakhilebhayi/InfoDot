<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ix-btn ix-btn--primary']) }}>
    {{ $slot }}
</button>

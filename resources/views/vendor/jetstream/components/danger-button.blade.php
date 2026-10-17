<button {{ $attributes->merge(['type' => 'button', 'class' => 'ix-btn ix-btn--danger']) }}>
    {{ $slot }}
</button>

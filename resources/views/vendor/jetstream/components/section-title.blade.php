<div class="flex justify-between md:col-span-1">
    <div>
        <h2 style="margin: 0; font-family: var(--ix-font); font-weight: 700; font-size: 18px; line-height: 1.3;">{{ $title }}</h2>

        <p style="margin: 6px 0 0; max-width: 40ch; font-size: 13px; color: var(--ix-text-2);">
            {{ $description }}
        </p>
    </div>

    <div>
        {{ $aside ?? '' }}
    </div>
</div>

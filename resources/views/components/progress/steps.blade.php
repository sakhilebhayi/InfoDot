{{--
    A step tracker (R-64 phase 5). `steps` is a list of ['title' => ..., 'done' => bool, 'note' => ?, 'url' => ?]; what is done comes from the database, never from
    a stored tick. The first step not done is marked as the current one. `variant="line"` lays the steps out as milestones along a line (stacked on a phone).
    `bar` adds the linear bar above with "N of M". Nothing is drawn without steps.
--}}
@props(['steps', 'label' => 'Progress', 'variant' => 'list', 'bar' => false])
@php
    $steps = array_values($steps);
    $figures = \App\Support\Progress::ofSteps($steps);
    $current = collect($steps)->search(fn ($s) => ! ($s['done'] ?? false));
@endphp
@if ($figures['total'] > 0)
    <div {{ $attributes->class(['ix-stepper']) }}>
        @if ($bar)
            <x-progress.bar :label="$label" :value="$figures['done']" :max="$figures['total']" :value-text="$figures['done'].' of '.$figures['total'].' done'" />
        @endif
        <ol class="ix-steps ix-steps--{{ $variant }}" aria-label="{{ $label }}">
            @foreach ($steps as $i => $step)
                @php($done = (bool) ($step['done'] ?? false))
                <li @class(['is-done' => $done, 'is-current' => $current === $i, 'is-todo' => ! $done && $current !== $i]) @if ($current === $i) aria-current="step" @endif>
                    <span class="ix-steps__mark" aria-hidden="true">@unless ($done){{ $i + 1 }}@endunless</span>
                    <span class="ix-steps__body">
                        <span class="ix-steps__title">@if (! empty($step['url']) && ! $done)<a href="{{ $step['url'] }}">{{ $step['title'] }}</a>@else{{ $step['title'] }}@endif
                            <span class="ix-sr">: {{ $done ? 'done' : ($current === $i ? 'next' : 'to do') }}</span></span>
                        @if (! empty($step['note']))<span class="ix-steps__note">{{ $step['note'] }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
@endif

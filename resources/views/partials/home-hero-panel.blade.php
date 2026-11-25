{{-- R-65: the product at work, in the hero. A real, published library entry (so nothing here is invented) and the same steps laid out as the tasks they
     become. The task layout is an example and says so. Without library entries nothing is drawn. --}}
@php
    $panelEntry = $library->first();
    $panelSteps = $panelEntry
        ? \App\Models\Steps::where('solution_id', $panelEntry->id)->orderBy('id')->limit(3)->pluck('solution_heading')
            ->map(fn ($h) => trim(preg_replace('/^step\s+\w+\s*[—:.\-]\s*/iu', '', is_array($h) ? (string) ($h[0] ?? '') : (string) $h)))->filter()->values()
        : collect();
@endphp
@if ($panelEntry && $panelSteps->isNotEmpty())
    <aside class="hd__panel" aria-label="A checked answer, and the work it becomes" data-hd-panel>
        <div class="hdp__answer">
            <p class="hdp__meta"><span class="ix-ref">{{ \App\Support\Reference::for($panelEntry) }}</span>
                <span class="pg-plate pg-plate--mustard" style="--pg-tone: var(--ix-knowledge)">{{ $panelEntry->isVerified() ? 'Verified' : 'Published' }}</span></p>
            <h2 class="hdp__title"><a href="{{ route('solutions.view', $panelEntry->id) }}">{{ $panelEntry->solution_title }}</a></h2>
            <p class="hdp__note">A solution is written as steps anyone can follow. Where a moderator who did not write it has checked it, it is marked verified.</p>
        </div>
        <div class="hdp__tasks">
            <p class="hdp__label">The same steps as tasks <span>Example</span></p>
            <ul class="hdp__list">
                @foreach ($panelSteps as $heading)
                    <li @class(['is-done' => $loop->first])><span class="hdp__box" aria-hidden="true"></span><span>{{ \Illuminate\Support\Str::limit($heading, 64) }}</span></li>
                @endforeach
            </ul>
        </div>
    </aside>
@endif

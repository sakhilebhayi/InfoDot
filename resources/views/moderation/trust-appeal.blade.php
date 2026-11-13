<x-app-layout>
    @include('livewire.moderation._tabs', ['current' => 'trust'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Appeal #{{ $appeal->id }}: {{ $appeal->team?->name }}</h1>
            <p class="ix-lede">Against {{ $appeal->subject_type === 'investigation' ? 'an investigation' : 'a verification decision' }}. Sent by {{ $appeal->appellant?->name ?? 'a former member' }} on {{ $appeal->created_at->format('j M Y') }}. <b>{{ \App\Models\BusinessAppeal::STATUSES[$appeal->status] }}</b>.</p>
        </div>
        <a href="{{ route('moderation.trust') }}" class="ix-btn ix-btn--ghost">All business trust</a>
    </div>

    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    <div class="ix-convo">
        <div class="ix-convo__main">
            <section class="ix-panel">
                <div class="ix-panel__head"><h2>What the business says</h2></div>
                <div class="ix-panel__body">
                    <p class="ix-msg__body">{{ $appeal->body }}</p>
                    @if ($files->isNotEmpty())<ul class="ix-msg__files">@foreach ($files as $f)<li><a href="{{ route('attachments.show', $f) }}" target="_blank" rel="noopener">{{ $f->name }}</a> <span class="ix-hint">{{ $f->sizeLabel() }}</span></li>@endforeach</ul>@endif
                </div>
            </section>

            @if ($appeal->isOpen() && $canDecide)
                <section class="ix-panel" aria-labelledby="dec-h">
                    <div class="ix-panel__head"><h2 id="dec-h">Decide</h2></div>
                    <div class="ix-panel__body ix-vactions">
                        @foreach (['uphold' => ['Uphold', 'ix-btn--primary', $appeal->subject_type === 'investigation' ? 'Clears the business and returns it to the directory.' : 'A suspension is lifted; after a rejection or revocation the business may apply again and reviewers will see this.'], 'deny' => ['Do not uphold', '', 'The decision stands. This was the business’s one appeal against it.']] as $decision => [$label, $class, $hint])
                            <details><summary class="ix-btn {{ $class }}">{{ $label }}</summary>
                                <form method="post" action="{{ route('moderation.trust.appeal.decide', [$appeal, $decision]) }}" class="ix-form ix-vgap">@csrf
                                    <p class="ix-hint">{{ $hint }}</p>
                                    <div class="ix-field"><label class="ix-label" for="n-{{ $decision }}">What to tell the business</label><textarea id="n-{{ $decision }}" name="note" class="ix-textarea" rows="3" minlength="20" maxlength="3000" required></textarea></div>
                                    <div><button class="ix-btn {{ $class }}">{{ $label }}</button></div>
                                </form>
                            </details>
                        @endforeach
                    </div>
                </section>
            @elseif ($appeal->isOpen())
                <p class="ix-alert" role="note">You made the decision being appealed, or you belong to the business, so another reviewer must decide this.</p>
            @else
                <section class="ix-panel"><div class="ix-panel__body"><p>{{ \App\Models\BusinessAppeal::STATUSES[$appeal->status] }} by {{ $appeal->decider?->name }} on {{ $appeal->decided_at?->format('j M Y') }}.</p><p class="ix-hint">{{ $appeal->decision_note }}</p></div></section>
            @endif
        </div>

        <aside class="ix-convo__rail">
            <section class="ix-biz__card">
                <h2>The decision appealed</h2>
                @if ($subject instanceof \App\Models\BusinessInvestigation)
                    <p><a href="{{ route('moderation.trust.investigation', $subject) }}">Investigation #{{ $subject->id }}</a></p>
                    <p class="ix-trust__line">{{ $subject->statusLabel() }}. {{ $subject->message_to_business }}</p>
                @elseif ($subject)
                    <p><a href="{{ route('moderation.verifications.show', $subject) }}">Verification #{{ $subject->id }}</a></p>
                    <p class="ix-trust__line">{{ $subject->label() }}.</p>
                @endif
            </section>
        </aside>
    </div>
</x-app-layout>

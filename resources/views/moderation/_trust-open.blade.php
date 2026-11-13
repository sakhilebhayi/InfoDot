{{-- Open an investigation of $team, from $reportIds and/or an identity conflict ($conflictId). --}}
<form method="post" action="{{ route('moderation.trust.open') }}" class="ix-form ix-vgap">
    @csrf
    <input type="hidden" name="team_id" value="{{ $team->id }}">
    @foreach ($reportIds ?? [] as $rid)<input type="hidden" name="report_ids[]" value="{{ $rid }}">@endforeach
    @isset($conflictId)<input type="hidden" name="conflict_id" value="{{ $conflictId }}">@endisset
    <div class="ix-field">
        <label class="ix-label" for="concern">Concern</label>
        <select id="concern" name="concern" class="ix-input">@foreach (\App\Models\BusinessInvestigation::CONCERNS as $k => $l)<option value="{{ $k }}" @selected(($concern ?? null) === $k)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="ix-field"><label class="ix-label" for="summary">What is being investigated (reviewers only)</label><textarea id="summary" name="summary" class="ix-textarea" rows="3" minlength="10" maxlength="3000" required></textarea></div>
    <label class="ix-choice__chk"><input type="checkbox" name="restrict" value="1"> Take the business out of the directory while this is investigated</label>
    <div class="ix-field"><label class="ix-label" for="message">Message to the business <span class="ix-hint">required when taking it out; what you are checking and what it can send. Never name who reported it.</span></label><textarea id="message" name="message" class="ix-textarea" rows="3" maxlength="2000"></textarea></div>
    <div><button class="ix-btn ix-btn--primary">Open the investigation</button></div>
</form>

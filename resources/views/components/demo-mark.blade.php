@props(['of' => null])
{{-- The demonstration marker (R-49): shown beside anything on the demonstration register (a business, person,
     question, solution, answer, comment or enquiry), wherever it appears. One component, so the words and the
     look are the same everywhere. `of` is a record, or a list of records when any of them makes the thing shown
     demonstration content. Nothing is drawn for real content. --}}
@if (collect(\Illuminate\Support\Arr::wrap($of))->contains(fn ($record) => $record instanceof \Illuminate\Database\Eloquent\Model && app(\App\Services\Demo\DemoRegistry::class)->has($record)))
    <span {{ $attributes->merge(['class' => 'ix-chip ix-chip--open']) }} title="Demonstration content: not a real business, person or record.">Demonstration</span>
@endif

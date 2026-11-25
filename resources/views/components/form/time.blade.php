{{--
    A time of day chosen from a list (R-64 phase 4), built on form.combobox: every `step` minutes between `from` and `to`, submitted as HH:MM, 24-hour.
    A stored time that is not on the list (09:10, or 09:30:00 from a database) is kept as its own choice, and the field can be left empty unless `required`,
    so saving never moves a time. For hours that can be any minute, use a plain time input instead.
--}}
@props(['name', 'id' => null, 'value' => null, 'step' => 15, 'from' => '00:00', 'to' => '23:45', 'required' => false])
@php
    $times = [];
    $start = (int) substr($from, 0, 2) * 60 + (int) substr($from, 3, 2);
    $end = (int) substr($to, 0, 2) * 60 + (int) substr($to, 3, 2);
    for ($m = $start; $m <= $end; $m += max(1, (int) $step)) {
        $label = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $times[$label] = $label;
    }
    $stored = $value === null || $value === '' ? null : substr((string) $value, 0, 5);
@endphp
<x-form.combobox :name="$name" :id="$id" :options="$times" :value="$stored" :required="$required" placeholder="hh:mm" empty="No such time" {{ $attributes }} />

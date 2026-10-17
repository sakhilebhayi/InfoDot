@props(['value' => 0, 'digits' => 2])
@php($n = (int) $value)
@php($padded = str_pad((string) $n, max($digits, strlen((string) $n)), '0', STR_PAD_LEFT))
@php($lead = strlen($padded) - strlen(ltrim($padded, '0')))
@php($lead = $n === 0 ? strlen($padded) - 1 : $lead)
<span class="ix-fig" {{ $attributes }}><span class="ix-sr">{{ $n }}</span><span aria-hidden="true">@if($lead > 0)<i>{{ str_repeat('0', $lead) }}</i>@endif{{ substr($padded, $lead) }}</span></span>

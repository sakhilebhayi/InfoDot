@component('mail::message')
Hello {{ $name }},

**{{ $heading }}**

@foreach ($lines as $line)
{{ $line }}

@endforeach
@component('mail::button', ['url' => $url])
Open the licences for {{ $company }}
@endcomponent

Nobody is removed from {{ $company }} and nothing is deleted. {{ $isOwner ? 'You can fix this yourself from the link above.' : 'Only the owner can buy or change licences, so please tell them.' }}
@endcomponent

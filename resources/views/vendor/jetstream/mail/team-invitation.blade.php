{{-- R-72 (E-05): the company name is typed by its owner, so it is shown as plain words, never as Markdown or a link. The way to accept
     comes first; creating an account is for someone who has none yet. --}}
@component('mail::message')
{{ __('You have been invited to join :team on InfoDot.', ['team' => \App\Support\MailText::safe((string) $invitation->team->name, 'a company', 120)]) }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('Accept the invitation') }}
@endcomponent

@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
{{ __('No InfoDot account yet?') }} [{{ __('Create one with this email address') }}]({{ route('register') }}){{ __(', then come back to this email and accept.') }}
@endif

{{ __('The invitation can be accepted for :days days. If you did not expect an invitation to this company, you may discard this email.', ['days' => config('licensing.invitation_days', 14)]) }}
@endcomponent

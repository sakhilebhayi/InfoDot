@component('mail::message')
Welcome{{ $name !== '' ? ', '.$name : '' }}.

Your InfoDot account is ready. Three good first steps:

- **Set up your company.** Give it a page people can find, and bring in the people who work with you.
- **Look something up.** [Search what other businesses have learned]({{ $searchUrl }}), or ask your own question.
- **Find a business you need.** [Browse the directory]({{ $businessesUrl }}), message a business or ask for an appointment.

@component('mail::button', ['url' => $homeUrl])
Open InfoDot
@endcomponent

You get this once, because you just joined. [Choose which emails you get]({{ $settingsUrl }}).
@endcomponent

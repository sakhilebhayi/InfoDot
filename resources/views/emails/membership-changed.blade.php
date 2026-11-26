@component('mail::message')
Hello{{ $name !== '' ? ' '.$name : '' }},

@if ($kind === 'added')
You were added to **{{ $company }}** on InfoDot. You can now see its questions, solutions and people, and use the platforms it has switched on.

@component('mail::button', ['url' => $url])
Open InfoDot
@endcomponent
@elseif ($kind === 'role_changed')
Your role in **{{ $company }}** on InfoDot is now **{{ $role }}**. What you can see and change in the company follows the new role from your next page.

@component('mail::button', ['url' => $url])
Open InfoDot
@endcomponent

If you did not expect this, ask the person who runs {{ $company }}.
@elseif ($kind === 'company_deleted')
**{{ $company }}** was deleted from InfoDot by its owner. Its pages, people and platforms are gone. Your own account, and your questions and answers outside the company, stay as they were.
@else
You were removed from **{{ $company }}** on InfoDot. You no longer see its people or its platforms. Your own questions, answers and account stay as they were.

If you think this was a mistake, ask the person who runs {{ $company }} to add you again.
@endif
@endcomponent

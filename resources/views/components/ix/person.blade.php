{{-- A person's name. It is a link to their profile only when the viewer may open that profile (their own, or someone in a shared company); otherwise the profile answers 404, so the name is plain text. --}}
@props(['user', 'name' => null])
@if ($user && $user->exists && $user->profileOpenTo(auth()->user()))<a {{ $attributes }} href="{{ route('profile.show', ['id' => $user->id]) }}">{{ $name ?? $user->name }}</a>@else<span {{ $attributes }}>{{ $name ?? $user?->name }}</span>@endif

{{-- One sheet on the concrete ground, under the area's top rule like every other panel. The brand is in the top bar,
     so the logo slot the framework passes is not drawn a second time.
     R-67: InfoDot's own sign-in and registration ($panel = 'login' or 'register') carry a graphite panel beside the
     sheet that says what the account opens. Platform-branded ways in pass no panel; they have their own. --}}
@props(['panel' => null])
@if ($panel)
    <div class="ix-entry__pair">
        <div class="ix-entry__card">
            {{ $slot }}
        </div>
        <x-auth.infodot-panel :kind="$panel" />
    </div>
@else
    <div class="ix-entry__card">
        {{ $slot }}
    </div>
@endif

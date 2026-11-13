<x-guest-layout title="Authorise">
    {{-- Registered first-party Dot platforms skip this screen (Client::skipsAuthorization). It
         renders only for a client that is not an active platform, so it is a safety net, not a
         normal step of ecosystem sign-in. --}}
    <div class="ix-entry__card">
        <h1 class="ix-auth-title">Authorise {{ $client->name }}</h1>

        <p class="ix-entry__lede">{{ $client->name }} is asking to confirm your identity through your InfoDot account.</p>

        @if (count($scopes) > 0)
            <p class="ix-entry__asks" id="asks-h">If you authorise it, {{ $client->name }} will be able to:</p>
            <ul class="ix-entry__scopes" aria-labelledby="asks-h">
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <div class="ix-entry__row">
            <form method="post" action="{{ route('passport.authorizations.approve', array_filter(['nonce' => request('nonce')])) }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="ix-btn ix-btn--primary">Authorise</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="ix-btn">Cancel</button>
            </form>
        </div>
    </div>
</x-guest-layout>

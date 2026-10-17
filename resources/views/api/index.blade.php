<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">API tokens</h1>
            <p class="ix-lede">Tokens let other services act on your behalf. Delete any you no longer use.</p>
        </div>
    </div>

    <div style="max-width: 980px;">
        @livewire('api.api-token-manager')
    </div>
</x-app-layout>

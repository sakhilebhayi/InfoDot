@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'p-4 rounded-lg bg-red-50 border border-red-200']) }}>
        <div class="font-medium text-sm text-red-700">{{ __('Whoops! Something went wrong.') }}</div>

        <ul class="mt-2 list-disc list-inside text-sm text-red-600">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))<p class="ix-alert" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

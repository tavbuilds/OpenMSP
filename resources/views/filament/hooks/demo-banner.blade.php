@auth
    @if (auth()->user()?->isDemo())
        <div class="omsp-banner">
            {{ __('Demo account — look around only. Adding or changing data is blocked.') }}
        </div>
    @endif
@endauth
@guest
    @include('filament.hooks.simple-locale')
@endguest

@auth
    @if (auth()->user()?->isDemo())
        <div class="w-full border-b border-amber-300 bg-amber-50 px-4 py-2 text-center text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            {{ __('Demo account — look around only. Adding or changing data is blocked.') }}
        </div>
    @endif
@endauth
@guest
    <div class="locale-switch-simple">
        @include('filament.hooks.language-switcher')
    </div>
    <style>
        .locale-switch-simple {
            position: absolute;
            top: 1rem;
            inset-inline-end: 1rem;
            z-index: 40;
        }
        @media (max-width: 480px) {
            .locale-switch-simple {
                position: relative;
                top: auto;
                inset-inline-end: auto;
                display: flex;
                justify-content: flex-end;
                padding: 0.75rem 1rem 0;
            }
        }
    </style>
@endguest

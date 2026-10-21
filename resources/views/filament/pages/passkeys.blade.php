<x-filament-panels::page>
    <x-filament::section :heading="__('Your passkeys')">
        @if ($this->isDemo())
            <p class="omsp-prose">
                {{ __('The demo account is shared, so it cannot hold a passkey — one visitor would be leaving a key behind for the next.') }}
            </p>
        @endif

        @forelse ($this->getPasskeys() as $passkey)
            <div class="omsp-passkey-row">
                <div>
                    <strong>{{ $passkey->alias ?: __('Passkey') }}</strong>
                    <div class="omsp-passkey-meta">
                        {{ __('Added :date', ['date' => $passkey->created_at?->translatedFormat('j F Y')]) }}
                    </div>
                </div>

                {{ ($this->removeAction)(['credential' => $passkey->getKey()]) }}
            </div>
        @empty
            <p class="omsp-prose">
                {{ __('No passkeys yet. Add one and you can sign in from this device without a password.') }}
            </p>
        @endforelse
    </x-filament::section>

    @if ($this->canRegister())
        <x-filament::section :heading="__('Add a passkey')">
            @include('filament.auth.passkey-enrol')
        </x-filament::section>
    @endif
</x-filament-panels::page>

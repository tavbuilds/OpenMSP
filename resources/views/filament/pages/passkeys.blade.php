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
                    <strong>{{ $passkey->name ?: __('Passkey') }}</strong>
                    <div class="omsp-passkey-meta">
                        {{-- The package names the authenticator from its
                             AAGUID where it recognises one, which is more
                             use than a date on its own. --}}
                        @if ($passkey->authenticator)
                            {{ $passkey->authenticator }} &middot;
                        @endif
                        {{ __('Added :date', ['date' => $passkey->created_at?->translatedFormat('j F Y')]) }}
                        @if ($passkey->last_used_at)
                            &middot; {{ __('Last used :date', ['date' => $passkey->last_used_at->diffForHumans()]) }}
                        @endif
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

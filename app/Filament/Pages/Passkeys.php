<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesNavigationGroup;
use App\Models\User;
use App\Support\Passkeys as PasskeyStore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Laravel\Passkeys\Actions\DeletePasskey;

/**
 * Where an operator adds and removes their own passkeys.
 *
 * Its own page rather than a section on the profile: the ceremony needs a
 * script of its own, and the list needs room to say which key is which.
 */
class Passkeys extends Page
{
    use TranslatesNavigationGroup;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $navigationLabel = 'Passkeys';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.passkeys';

    public function getTitle(): string|Htmlable
    {
        return __('Passkeys');
    }

    public static function getNavigationLabel(): string
    {
        return __('Passkeys');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Sign in with your fingerprint, face, or device PIN instead of typing a password. Your password keeps working — a passkey is a second way in, not a replacement.');
    }

    /** @return Collection<int, WebAuthnCredential> */
    public function getPasskeys(): Collection
    {
        return PasskeyStore::for($this->user());
    }

    public function canRegister(): bool
    {
        return PasskeyStore::canRegister($this->user());
    }

    public function isDemo(): bool
    {
        return $this->user()->isDemo();
    }

    public function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('Remove'))
            ->color('danger')
            ->link()
            ->requiresConfirmation()
            ->modalHeading(__('Remove this passkey?'))
            ->modalDescription(__('The device will no longer sign you in. You can always add it again.'))
            ->disabled(fn (): bool => $this->isDemo())
            ->action(function (array $arguments): void {
                if ($this->isDemo()) {
                    return;
                }

                $passkey = $this->user()->passkeys()
                    ->whereKey($arguments['credential'] ?? null)
                    ->first();

                if ($passkey === null) {
                    return;
                }

                // Through the package's own action, so its event fires and
                // the audit entry is written in one place.
                app(DeletePasskey::class)($this->user(), $passkey);

                Notification::make()->title(__('Passkey removed'))->success()->send();
            });
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

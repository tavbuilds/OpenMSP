<?php

namespace App\Filament\Auth\MultiFactor;

use App\Models\User;
use App\Support\Passkeys;
use Closure;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A passkey as the second factor.
 *
 * Without this, turning on "require 2FA" would lock out an operator who signs
 * in with a passkey: Filament would see no factor it recognises and send them
 * off to set up an authenticator app, forever. A passkey already proves
 * possession of the device and whatever unlocks it, which is the thing a
 * second factor is for.
 *
 * Someone who has a passkey but signs in with their password still gets
 * challenged — the challenge is the passkey.
 */
class PasskeyAuthentication implements MultiFactorAuthenticationProvider
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'passkey';
    }

    public function getLoginFormLabel(): string
    {
        return __('Passkey');
    }

    public function isEnabled(Authenticatable $user): bool
    {
        return $user instanceof User && Passkeys::has($user);
    }

    /**
     * The enrolment form, shown wherever Filament manages factors.
     *
     * It has to be here and not only on the Passkeys page: when 2FA is
     * required and the account has no factor yet, Filament pins the operator
     * to its set-up screen, and every other page — including that one —
     * bounces them back. Without this, the only factor they could ever
     * enrol would be an authenticator app.
     *
     * @return array<Component>
     */
    public function getManagementSchemaComponents(): array
    {
        $user = auth()->user();
        $count = $user instanceof User ? Passkeys::count($user) : 0;

        return [
            Section::make(__('Passkey'))
                ->description(__('Your fingerprint, face, or device PIN. Counts as the second step when 2FA is required.'))
                ->afterHeader($count > 0
                    ? Text::make(trans_choice('{1} :count passkey|[2,*] :count passkeys', $count, ['count' => $count]))
                        ->badge()
                        ->color('success')
                    : Text::make(__('None yet'))->badge())
                ->schema([
                    View::make('filament.auth.passkey-enrol')
                        ->visible(fn (): bool => $user instanceof User && Passkeys::canRegister($user)),
                    Text::make(__('Manage the ones you already have under System → Passkeys.'))
                        ->visible(fn (): bool => $count > 0),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public function getChallengeFormComponents(Authenticatable $user): array
    {
        /** @var User $user */
        $options = Passkeys::challengeOptionsFor($user);

        return [
            View::make('filament.auth.passkey-challenge')
                ->viewData(['options' => $options]),
            Text::make(__('Your passkey is the second step. Use it to finish signing in.')),
            // The script fills this in and submits. It is required, so the
            // form cannot be passed by simply pressing the button.
            Hidden::make('assertion')
                ->required()
                ->rule($this->assertionRule($user)),
        ];
    }

    private function assertionRule(User $user): Closure
    {
        // The outer closure is what Filament evaluates to get the rule, so
        // it has to carry the user through to the one that checks.
        return function () use ($user): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                $assertion = is_string($value) ? json_decode($value, true) : null;

                if (! is_array($assertion) || ! Passkeys::verifyChallenge($user, $assertion)) {
                    $fail(__('That passkey was not recognised.'));
                }
            };
        };
    }
}

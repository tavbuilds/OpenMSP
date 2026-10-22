<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AuthLockout;
use App\Support\Passkeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkey;

/**
 * The rules this platform puts around laravel/passkeys.
 *
 * The package handles the ceremonies; what it cannot know is who is allowed
 * to sign in with one here, and what belongs in the audit log.
 */
class PasskeyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Laravel\Passkeys\Passkeys::authorizeLoginUsing(
            fn (Request $request, PasskeyUser $user, Passkey $passkey): bool => $this->allows($request, $user),
        );

        Event::listen(PasskeyRegistered::class, function (PasskeyRegistered $event): void {
            if ($event->user instanceof User) {
                Passkeys::audit($event->user, 'passkey.registered', ['name' => $event->passkey->name]);
            }
        });

        Event::listen(PasskeyDeleted::class, function (PasskeyDeleted $event): void {
            if ($event->user instanceof User) {
                Passkeys::audit($event->user, 'passkey.removed', ['name' => $event->passkey->name]);
            }
        });

        Event::listen(PasskeyVerified::class, function (PasskeyVerified $event): void {
            if ($event->user instanceof User) {
                Passkeys::audit($event->user, 'passkey.used', ['name' => $event->passkey->name]);
            }
        });
    }

    /**
     * The last word before a passkey signs somebody in.
     *
     * The ceremony is discoverable — no account is named — so the lockout
     * can only count against the caller's address. That is enough: without
     * an account name there is nothing to spray, and an assertion the
     * authenticator never signed cannot be guessed.
     */
    private function allows(Request $request, PasskeyUser $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // A shared account must not be reachable by a key one visitor left
        // behind, however it came to hold one.
        if ($user->isDemo()) {
            return false;
        }

        try {
            AuthLockout::assert($request, null);
        } catch (\Throwable) {
            return false;
        }

        AuthLockout::clear($request, null);

        return true;
    }
}

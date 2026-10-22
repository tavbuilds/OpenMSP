<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkeys for the admin panel.
 *
 * The ceremonies, the routes and the storage come from laravel/passkeys.
 * What lives here is the part that is this platform's: who may hold one,
 * what goes in the audit log, and the second-factor challenge, which
 * Filament asks for in a shape the package does not cover.
 *
 * A passkey sits beside the password rather than replacing it: an operator
 * who loses their phone must still be able to get in. It also counts as the
 * second factor when 2FA is required, because the ceremony already proves
 * possession of the device and whatever unlocks it.
 */
final class Passkeys
{
    /** How many a single account may hold. A laptop, a phone, a spare key. */
    public const LIMIT = 10;

    /** Where the options that go with the live challenge are kept. */
    private const CHALLENGE_OPTIONS_KEY = 'passkey.challenge_options';

    /** @return Collection<int, Passkey> */
    public static function for(User $user): Collection
    {
        return $user->passkeys()->orderByDesc('created_at')->get();
    }

    public static function count(User $user): int
    {
        return $user->passkeys()->count();
    }

    public static function has(User $user): bool
    {
        return $user->hasPasskeysEnabled();
    }

    /**
     * Whether this account may add one.
     *
     * The demo account is shared and read-only, and a passkey registered on
     * it would hand one visitor a key the next visitor cannot remove.
     */
    public static function canRegister(User $user): bool
    {
        return ! $user->isDemo() && self::count($user) < self::LIMIT;
    }

    /** A name the operator will recognise in six months. */
    public static function cleanAlias(?string $alias): string
    {
        $alias = trim((string) $alias);

        return $alias === '' ? __('Passkey') : mb_substr($alias, 0, 60);
    }

    /**
     * A challenge for this one account, for the second-factor prompt.
     *
     * Not discoverable: we already know who is signing in, and naming their
     * credentials keeps the browser from offering a key belonging to
     * somebody else on the same machine.
     *
     * Minted once and then reused while it lives. Filament rebuilds the
     * challenge form in order to validate it, so a generator that ran on
     * every build would replace the challenge the browser had just signed,
     * and the sign-in would fail every single time. The package pulls the
     * options out of the session when it checks one, so a retry gets a
     * fresh pair without any help from here.
     *
     * @return array<string, mixed>
     */
    public static function challengeOptionsFor(User $user): array
    {
        $sessionKey = self::CHALLENGE_OPTIONS_KEY.'.'.$user->getKey();

        if (session()->has('passkey.verification_options') && is_array($cached = session()->get($sessionKey))) {
            return $cached;
        }

        $options = app(GenerateVerificationOptions::class)($user);

        session()->put('passkey.verification_options', WebAuthn::toJson($options));

        $browser = WebAuthn::toBrowserArray($options);
        session()->put($sessionKey, $browser);

        return $browser;
    }

    /**
     * Checks an assertion for this user against the challenge in session.
     *
     * @param  array<string, mixed>  $credential
     */
    public static function verifyChallenge(User $user, array $credential): bool
    {
        $serialized = session()->pull('passkey.verification_options');

        if (! is_string($serialized) || $serialized === '') {
            return false;
        }

        try {
            app(VerifyPasskey::class)(
                WebAuthn::fromJson(json_encode($credential) ?: '{}', PublicKeyCredential::class),
                WebAuthn::fromJson($serialized, PublicKeyCredentialRequestOptions::class),
                $user,
            );
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Passkey events go in the audit log against the user: what changed is
     * how this account can be signed into.
     *
     * @param  array<string, mixed>  $details
     */
    public static function audit(User $user, string $event, array $details = []): void
    {
        AuditLog::create([
            'user_id' => $user->getKey(),
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->getKey(),
            'old_values' => [],
            'new_values' => $details,
            'ip_address' => request()?->ip(),
        ]);
    }
}

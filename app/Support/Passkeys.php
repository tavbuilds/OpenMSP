<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\JsonTransport;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Throwable;

/**
 * Passkeys for the admin panel.
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

    /** @return Collection<int, WebAuthnCredential> */
    public static function for(User $user): Collection
    {
        return $user->webAuthnCredentials()
            ->whereNull('disabled_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public static function count(User $user): int
    {
        return $user->webAuthnCredentials()->whereNull('disabled_at')->count();
    }

    public static function has(User $user): bool
    {
        return self::count($user) > 0;
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

    public static function forget(User $user, string $credentialId): bool
    {
        $credential = $user->webAuthnCredentials()->whereKey($credentialId)->first();

        if ($credential === null) {
            return false;
        }

        $alias = $credential->alias;
        $credential->delete();

        self::audit($user, 'passkey.removed', ['alias' => $alias]);

        return true;
    }

    /** Where the options that go with the live challenge are kept. */
    private const CHALLENGE_OPTIONS_KEY = 'passkey.challenge_options';

    /**
     * A challenge for this one account, for the second-factor prompt.
     *
     * The ceremony there is not discoverable: we already know who is signing
     * in, and naming their credentials keeps the browser from offering a key
     * belonging to somebody else on the same machine.
     *
     * Minted once and then reused while it lives. Filament rebuilds the
     * challenge form to validate it, so a generator that ran on every build
     * would replace the challenge the browser had just signed — and the
     * sign-in would fail with "challenge is not equal" every single time.
     * The package pulls the challenge out of the session when it checks one,
     * so a retry gets a fresh pair without any help from here.
     *
     * @return array<string, mixed>
     */
    public static function assertionOptionsFor(User $user): array
    {
        $sessionKey = self::CHALLENGE_OPTIONS_KEY.'.'.$user->getKey();
        $live = session()->has((string) config('webauthn.challenge.key'));

        if ($live && is_array($cached = session()->get($sessionKey))) {
            return $cached;
        }

        $json = app(AssertionRequest::class)
            ->toVerify($user)
            ->toResponse(request())
            ->getData(true);

        $json = is_array($json) ? $json : [];
        session()->put($sessionKey, $json);

        return $json;
    }

    /**
     * Checks an assertion against the challenge in the session.
     *
     * @param  array<string, mixed>  $assertion
     */
    public static function verifyAssertion(User $user, array $assertion): bool
    {
        try {
            app(AssertionValidator::class)
                ->send(new AssertionValidation(new JsonTransport($assertion), user: $user))
                ->thenReturn();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Passkey events go in the audit log against the user, not the
     * credential: the credential id is a string and the log keys on integers,
     * and what actually changed is how this account can be signed into.
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

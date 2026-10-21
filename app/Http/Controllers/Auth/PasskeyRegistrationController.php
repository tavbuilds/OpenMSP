<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Passkeys;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enrolling a passkey.
 *
 * Both steps sit behind the panel's own auth middleware and a fresh password
 * confirmation: adding a key is adding a way in, so an unattended session
 * must not be enough to do it.
 */
class PasskeyRegistrationController extends Controller
{
    public function options(AttestationRequest $request): Responsable
    {
        $this->assertMayRegister($request->user());

        return $request
            // Ask for a discoverable credential so the operator can sign in
            // without typing an email first — the whole point of the button
            // on the login screen.
            ->userless()
            ->fastRegistration()
            ->toCreate();
    }

    public function store(AttestedRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertMayRegister($user);

        // The ceremony proves the device is present, not that the person at
        // the keyboard is the account holder. An unattended session is a way
        // in; it must not be a way to mint a second one.
        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return response()->json([
                'message' => __('That password is not correct.'),
            ], 422);
        }

        $alias = Passkeys::cleanAlias($request->input('alias'));

        $request->save(['alias' => $alias]);

        Passkeys::audit($user, 'passkey.registered', ['alias' => $alias]);

        return response()->json([
            'ok' => true,
            'message' => __('Passkey added.'),
        ]);
    }

    private function assertMayRegister(?User $user): void
    {
        if ($user === null) {
            throw new HttpException(403, __('Sign in first.'));
        }

        if ($user->isDemo()) {
            throw new HttpException(403, __('The demo account cannot add a passkey.'));
        }

        if (! Passkeys::canRegister($user)) {
            throw new HttpException(422, __('This account already has the maximum number of passkeys.'));
        }
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\TooManyAuthAttemptsException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthLockout;
use App\Support\Passkeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

/**
 * Signing in with a passkey.
 *
 * The ceremony is discoverable — no email is typed — so the lockout here can
 * only count against the caller's address. That is enough: without an
 * account name there is nothing to spray, and an assertion the authenticator
 * never signed cannot be forged by guessing.
 */
class PasskeyLoginController extends Controller
{
    public function options(AssertionRequest $request): JsonResponse
    {
        if ($locked = $this->lockedOut($request)) {
            return $locked;
        }

        return response()->json($request->toVerify()->toResponse($request)->getData());
    }

    public function login(AssertedRequest $request): JsonResponse
    {
        if ($locked = $this->lockedOut($request)) {
            return $locked;
        }

        $user = $request->login();

        if (! $user instanceof User) {
            AuthLockout::hit($request, null);

            return response()->json([
                'message' => __('That passkey was not recognised.'),
            ], 422);
        }

        AuthLockout::clear($request, null);
        Passkeys::audit($user, 'passkey.used');

        return response()->json([
            'redirect' => filament()->getPanel('admin')->getUrl(),
        ]);
    }

    /**
     * The shared lockout speaks in Filament validation errors, which mean
     * nothing to a fetch(). Translate it into something the button can show.
     */
    private function lockedOut(Request $request): ?JsonResponse
    {
        try {
            AuthLockout::assert($request, null);
        } catch (TooManyAuthAttemptsException) {
            return response()->json([
                'message' => __('Too many attempts. Try again in a few minutes.'),
            ], 429);
        }

        return null;
    }
}

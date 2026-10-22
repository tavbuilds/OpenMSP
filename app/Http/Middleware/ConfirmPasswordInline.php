<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks the account password on the passkey management routes.
 *
 * Laravel's own `password.confirm` redirects to a confirmation page, which
 * this panel has no route for, and a redirect is no use to the fetch() that
 * runs the ceremony anyway. The password is asked for in the enrolment form
 * instead and checked here: the browser ceremony proves the device is
 * present, not that the person at the keyboard owns the account, and adding
 * a passkey is adding a way in.
 *
 * Removal is exempt. A key its holder can no longer use is not worth
 * keeping, and making removal harder than enrolment is the wrong way round.
 */
class ConfirmPasswordInline
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE') || $request->isMethod('GET')) {
            return $next($request);
        }

        $user = $request->user();
        $password = (string) $request->input('password');

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return response()->json([
                'message' => __('That password is not correct.'),
            ], 422);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\LocaleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(LocaleCatalog::isSupported($locale), 404);

        $matched = LocaleCatalog::match($locale) ?? LocaleCatalog::DEFAULT;

        // The cookie serves this browser; the account serves everything
        // written to them without one — a reminder sent at eight in the
        // morning by the scheduler has no cookie to read.
        $account = Auth::guard('web')->user() ?? Auth::guard('portal')->user();
        $account?->forceFill(['language' => $matched])->save();

        return redirect()
            ->back(fallback: '/admin')
            ->withCookie(cookie(
                name: LocaleCatalog::COOKIE,
                value: $matched,
                minutes: 60 * 24 * 365,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
    }
}

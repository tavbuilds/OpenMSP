<?php

namespace App\Http\Controllers;

use App\Support\LocaleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(LocaleCatalog::isSupported($locale), 404);

        $matched = LocaleCatalog::match($locale) ?? LocaleCatalog::DEFAULT;

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

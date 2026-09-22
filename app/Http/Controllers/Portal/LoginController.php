<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Notifications\PortalMagicLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('portal')->check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.auth.login');
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));
        $key = 'portal-magic:'.$request->ip().':'.$email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withInput()
                ->withErrors(['email' => __('Too many requests. Try again in :seconds seconds.', ['seconds' => $seconds])]);
        }

        RateLimiter::hit($key, 60);

        $contact = Contact::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        // Always show the same message (no email enumeration).
        $message = __('If this email is on file, you will receive a sign-in link shortly.');

        if ($contact && $contact->canUsePortal()) {
            $expires = now()->addMinutes(30);
            $url = URL::temporarySignedRoute(
                'portal.magic',
                $expires,
                ['contact' => $contact->id]
            );

            // The contact has no stored language, so send the mail in the one
            // they are reading the portal in right now.
            $contact->notify((new PortalMagicLink($url, 30))->locale(app()->getLocale()));
        }

        return back()->with('status', $message);
    }

    public function magic(Request $request, Contact $contact): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('portal.login')
                ->withErrors(['email' => __('This sign-in link is invalid or has expired. Request a new one.')]);
        }

        if (! $contact->canUsePortal()) {
            abort(403);
        }

        Auth::guard('portal')->login($contact, remember: true);
        $request->session()->regenerate();

        $contact->forceFill(['portal_last_login_at' => now()])->save();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}

# Passkeys

Signing in to the admin panel with a fingerprint, a face, or a device PIN
instead of typing a password.

A passkey sits **beside** the password, not in place of it. An operator who
loses their phone has to be able to get back in, and a platform that can lock
out its only administrator is not a platform anyone should run their business
on. The password form never goes away.

## What an operator does

1. **System → Passkeys → Add a passkey.**
2. Give it a name — *Work laptop*, *iPhone* — and type the account password.
3. The browser asks the device for a fingerprint, face, or PIN.

From then on the login screen offers **Sign in with a passkey** below the
password form. No email is typed: the key is discoverable, so the device
offers the account it holds.

Up to ten keys per account. Remove one from the same page; the device stops
signing you in immediately.

## Why the password is asked for when adding one

The ceremony proves the device is present. It does not prove the person at
the keyboard is the account holder — an unattended session would be enough.
Since adding a passkey is adding a way in, the account password is required
to do it, and the event lands in the audit log.

Removing one does not ask: a key the holder cannot use is not a key worth
keeping, and making removal harder than addition is the wrong way round.

## Passkeys and "require 2FA"

Turning on **Settings → Require 2FA for all administrators** treats a passkey
as a factor, because the ceremony already proves possession of the device and
whatever unlocks it.

- **Signed in with a passkey** → nothing more is asked.
- **Signed in with a password, passkey enrolled** → the second step is the
  passkey. One prompt, no codes to type.
- **Signed in with a password, authenticator app enrolled** → the code, as
  before.

The enrolment form also appears on Filament's own *set up two-factor
authentication* screen. It has to: with 2FA required and no factor yet, that
screen is the only page an operator can reach, and without it the only factor
they could ever enrol would be an authenticator app.

## The demo account

Shared and read-only, so it holds no passkeys — one visitor would be leaving
a key behind for the next.

## What it needs to work

- **HTTPS**, or `localhost` for development. Browsers refuse the ceremony on
  a plain-HTTP origin.
- A **stable hostname**. The key is bound to the domain it was created on, so
  a passkey made on `msp.example.com` will not work on an IP address or a
  different host. Set `WEBAUTHN_ID` if the panel is served from a subdomain
  and the key should cover the parent.
- A browser from the last few years. The page checks and hides the offer
  rather than presenting a button that cannot work.

## How it is wired

| Piece | Where |
|---|---|
| Ceremonies, routes, storage | `laravel/passkeys` — `/passkeys/login`, `/user/passkeys` |
| Who may sign in; audit entries | `app/Providers/PasskeyServiceProvider.php` |
| Password check on enrolment | `app/Http/Middleware/ConfirmPasswordInline.php` |
| Limits, challenge, audit helper | `app/Support/Passkeys.php` |
| Second factor | `app/Filament/Auth/MultiFactor/PasskeyAuthentication.php` |
| Management page | `app/Filament/Pages/Passkeys.php` |
| Browser side | `resources/views/filament/hooks/passkey-script.blade.php` |
| Credentials | `passkeys` table |

Built on **laravel/passkeys**, which supplies the ceremonies, the routes,
the storage and the throttling. What this application adds is the part the
package cannot know: who may hold a key, what belongs in the audit log, and
the second-factor challenge, which Filament asks for in a shape of its own.

Sign-in is throttled by the package and checked again against the same
lockout as the password form, counted against the caller's address — the
discoverable ceremony names no account, so there is nothing to spray.

The package's `password.confirm` default would redirect to a confirmation
page this panel has no route for, and a redirect is no use to the `fetch()`
running the ceremony. `ConfirmPasswordInline` checks a password sent with
the enrolment form instead, which is also the better flow: one form, no
detour.

### Notes for anyone changing this

The challenge is minted **once per attempt** and cached in the session, in
`Passkeys::challengeOptionsFor()`. Filament rebuilds the challenge form in
order to validate it, so a generator that ran on every build would replace
the challenge the browser had just signed, and every sign-in would fail with
*"challenge is not equal"*. The package pulls the options out of the
session when it checks one, so a retry gets a fresh pair on its own.

The challenge view uses **Alpine, not a plain `<script>`**. Filament swaps it
in over the login form through Livewire, and a script that arrives by DOM
morph never executes. It also sets the hidden field with a *deferred*
`$wire.set(..., false)`: a live set makes a round trip, that round trip
re-renders the form, and the re-render would invalidate the assertion just
signed.

## History

This started on `laragear/webauthn`, which was marked abandoned shortly
after — pointing at `laravel/passkeys`, the official package. The move
happened before anyone had come to depend on it.

The two store a credential differently enough that converting one into the
other was not worth writing for a feature days old, so the old
`webauthn_credentials` table is dropped rather than left holding key
material nothing reads. **Anyone who enrolled a passkey before that adds it
again.** Nobody is locked out by it: the password form never went away,
which is the whole reason passkeys were built beside it.

`laravel/passkeys` is pre-1.0, so its API may still move. That is the cost
of being on the package that will be maintained rather than the one that
will not.

# First-run setup (onboarding)

An empty database has **no demo account** unless `APP_DEMO_LOGIN=true`.
The UI walks you through two steps: an administrator account, then platform
settings. A demo user (`test` / `test`) does not count as an administrator
and is removed when the first real account is created.

Existing installs (users already in the database) skip the wizard: the
migration sets `onboarding.completed`. You can always restart it from
**Settings → Restart onboarding**.

The admin UI is English-native. It follows the browser language, then falls
back to English. Pick another language with the switcher on the login screen
or in the admin top bar. See the Languages section in the [README](../README.md#languages).

## 1. Administrator account

Open `/admin`. While no real administrator exists you see **Create
administrator account** (or a sign-in screen if a demo user is present, with
a link to first install). That first real user gets the `admin` role.
Registration then closes; create further accounts under **System → Users**.

## 2. Onboarding wizard (`/admin/setup`)

After the first sign-in (admin/manager only):

1. **Branding** — platform name, primary color, logo, and favicon (admin, login, portal).
2. **Email** — `log` until SMTP is ready; change later under Settings.
3. **Stripe** — optional, for portal auto-collect.
4. **API token** — optional first Sanctum token (shown once).

**Skip, set this later** finishes the wizard with defaults.

Until onboarding is complete, admin/manager pages redirect to `/admin/setup`.
Restart anytime: **Settings → Restart onboarding**.

## 3. Admin and reset (no tinker)

| What | Where |
|------|-------|
| Users + roles | System → Users |
| Reset password | Edit user → Reset password (revokes tokens) |
| Own API tokens | System → API tokens |
| Another user’s tokens | User → API tokens relation |
| Revoke every token | Settings → Revoke all API tokens |
| SMTP / Stripe | Settings (empty = env `MAIL_*` / `STRIPE_*`) |
| Branding (logo, color) | Settings → Branding |
| Load / wipe demo data | System → Demo data (or `php artisan demo:seed` / `demo:purge`) |
| Audit log | System → Audit log |
| CSV export | Contracts / Customers / Endpoints → Export CSV |
| Endpoints / certificates | Endpoints (webhook per monitor, see [ENDPOINTS.md](ENDPOINTS.md)) |
| Revert to env | Settings → Reset email/Stripe to env |
| Require 2FA | Settings → Require 2FA for all administrators |

The **last administrator** cannot be deleted or demoted.

## Deploy

After `php artisan migrate` (or the production entrypoint that already does
that) setup is ready in the browser. See [DEPLOY.md](../DEPLOY.md) and
[SECRETS.md](SECRETS.md). `APP_KEY` stays a runtime secret: it encrypts
license keys, 2FA, and UI secrets (SMTP password, Stripe secret).

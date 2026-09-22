# Secrets & production environment

Secrets do **not** belong in Git. Set them in Coolify / Portainer, or in a
local `.env` that is in `.gitignore`. Examples:

- [`.env.example`](../.env.example) — local `docker compose`
- [`.env.production.example`](../.env.production.example) — production stack

See [DEPLOY.md](../DEPLOY.md) for CLI, Coolify, Portainer, and droplet.

`docker-compose.prod.yml` passes that production list **identically** to
`app`, `queue`, and `scheduler`.

You can also set SMTP, Stripe, and the platform name **from the admin UI**
(System → Settings). Those values override env until you reset them back to
env. UI secrets (SMTP password, Stripe secret) are encrypted with `APP_KEY`
in the `settings` table.

## Stable secrets (critical)

| Variable | Why keep it stable |
|----------|--------------------|
| `APP_KEY` | Encrypts license keys **and** Filament 2FA secrets / recovery codes **and** UI secrets in `settings`. Changing it makes existing ciphertext unreadable. Generate **once**, store safely, reuse on every redeploy. |
| `DB_PASSWORD` | PostgreSQL password for the `db` service. Changing it without updating the volume/DB user means the app cannot connect. |

### Generate APP_KEY (once)

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Put the result (starts with `base64:`) in the host env as `APP_KEY`. **Never**
run `php artisan key:generate` again on an existing production stack.

## Required env vars

Minimum to start `docker-compose.prod.yml`:

| Name | Example / note |
|------|----------------|
| `APP_KEY` | `base64:…` (stable, see above) |
| `DB_PASSWORD` | strong, unique password |
| `APP_URL` | `https://msp.example.com` or `http://<host>:8090` |
| `APP_PORT` | host port, default `8090` |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS, otherwise `false` |

Optional: `DB_DATABASE`, `DB_USERNAME` (default `msp`/`msp`), `APP_NAME`,
`APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_TIMEZONE` (default `UTC`; reminder
jobs fire at 08:00 in this zone), `APP_DEMO_LOGIN` (default `false`).

## SMTP (`MAIL_*`)

Laravel reads mail from env (`config/mail.php`). Compose passes these through
to `app`, `queue`, and `scheduler` — secrets are **not** in the image.

| Variable | Production example |
|----------|--------------------|
| `MAIL_MAILER` | `smtp` (locally often `log`) |
| `MAIL_HOST` | `smtp.yourdomain.com` |
| `MAIL_PORT` | `587` (STARTTLS) or `465` (implicit TLS) |
| `MAIL_USERNAME` | SMTP user |
| `MAIL_PASSWORD` | SMTP password / app password |
| `MAIL_SCHEME` | empty/`null` on port 587; `smtps` on port 465 (Laravel “encryption”) |
| `MAIL_FROM_ADDRESS` | `msp@yourdomain.com` |
| `MAIL_FROM_NAME` | `MSP Platform` (or your platform name; the UI overrides this) |

Renewal reminders, certificate/planning reminders, and portal magic-link mail
use the same mail config.

### Send a test mail

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="Mail::raw('MSP SMTP test', fn (\$m) => \$m->to('you@example.com')->subject('MSP SMTP test'));"
```

With `MAIL_MAILER=log`, check the Laravel log instead of the inbox.

## Stripe (customer portal)

Needed for automatic SEPA collection via iDEAL and invoice mirroring.
See [PORTAL.md](PORTAL.md).

| Variable | Note |
|----------|------|
| `STRIPE_KEY` | Publishable key (`pk_test_…` / `pk_live_…`) |
| `STRIPE_SECRET` | Secret key (`sk_test_…` / `sk_live_…`) |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_…`) for `POST /stripe/webhook` |

These are passed into **all** PHP services. You can also set them from
System → Settings after first login.

Locally: `stripe listen --forward-to localhost:8090/stripe/webhook` and put
the printed `whsec_` in `.env`. Enable iDEAL + SEPA Direct Debit in the
Stripe Dashboard (test and live).

## Demo login

`APP_DEMO_LOGIN=true` creates the view-only demo account on an empty database:
sign in with `test@demo.local` / `test` (see the README). Leave it `false` on a
real MSP install.
Passed through compose so Coolify/Portainer env actually reaches the app.

## Filament 2FA (TOTP)

App authentication (Google Authenticator / Authy / Microsoft Authenticator) is
enabled on the admin panel, **optional** per user.

- Setup: sign in → **Profile** → link an authenticator app (QR + recovery codes).
- Secrets live in `users.app_authentication_secret` /
  `users.app_authentication_recovery_codes` (encrypted with `APP_KEY`).
- **Sanctum API tokens** are unchanged; MFA applies only to Filament login.
- The **customer portal** (`/portal`) uses magic links, not 2FA.

### Require 2FA (admin/manager)

MFA is not required by default. Turn on **Require 2FA for all administrators**
under **System → Settings**. Sanctum API tokens still work without TOTP.

## Checklist before the first production deploy

1. `APP_KEY` generated and stored safely (password manager / vault).
2. `DB_PASSWORD` strong and unique.
3. Host env set from `.env.production.example`; **no** real secrets in Git.
4. (Optional) SMTP tested with artisan/`Mail::raw`.
5. (Optional) Stripe keys + webhook secret set; iDEAL/SEPA enabled.
6. After deploy: first account + onboarding wizard, then Profile → 2FA and store recovery codes.

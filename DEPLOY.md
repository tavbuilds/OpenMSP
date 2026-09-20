# Deploy with Portainer (Raspberry Pi)

The production stack ([docker-compose.prod.yml](docker-compose.prod.yml)) bakes
the code into the image, migrates on boot, and reads secrets from environment
variables. No bind mounts — a good fit for Portainer.

Full secret / SMTP / 2FA notes: [docs/SECRETS.md](docs/SECRETS.md).

## Stripe for the customer portal (optional)

The portal payment flow needs `STRIPE_KEY`, `STRIPE_SECRET`, and
`STRIPE_WEBHOOK_SECRET`. Add them as runtime secrets in Portainer; see
[docs/SECRETS.md](docs/SECRETS.md) and [docs/PORTAL.md](docs/PORTAL.md).

## Requirements

- Docker + Portainer on the Raspberry Pi (arm64; Pi 4/5 with 4GB+ recommended).
- The code in a Git repository (GitHub/GitLab/self-hosted). Portainer builds
  the image from that repo, so the build context is available.

## Step 1 — Generate an APP_KEY

This key encrypts license keys **and** Filament 2FA secrets.
**Generate it once and keep it stable** (if it changes, encrypted data
becomes unreadable):

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copy the output (starts with `base64:`). Store it in a password manager;
**do not** run `key:generate` again on an existing production stack.

## Step 2 — Create the stack in Portainer

1. In Portainer go to **Stacks → Add stack**.
2. Name: e.g. `msp-platform`.
3. Build method: **Repository**.
   - **Repository URL**: your Git repo URL.
   - **Repository reference**: `refs/heads/main` (or your branch).
   - **Compose path**: `docker-compose.prod.yml`.
   - Turn **Authentication** on if the repo is private (token/deploy key).
4. Scroll to **Environment variables** and add:

   | Name | Value |
   |------|--------|
   | `APP_KEY` | the key from step 1 (**keep it stable**) |
   | `DB_PASSWORD` | a strong, unique password |
   | `APP_URL` | `http://<pi-ip>:8090` (or your domain) |
   | `APP_PORT` | `8090` (host port) |
   | `SESSION_SECURE_COOKIE` | `false` (or `true` behind HTTPS) |

   Optional, for real reminder email (see [docs/SECRETS.md](docs/SECRETS.md)):

   | Name | Value |
   |------|--------|
   | `MAIL_MAILER` | `smtp` |
   | `MAIL_HOST` | SMTP host |
   | `MAIL_PORT` | `587` or `465` |
   | `MAIL_USERNAME` | SMTP user |
   | `MAIL_PASSWORD` | SMTP password |
   | `MAIL_SCHEME` | empty on 587; `smtps` on 465 |
   | `MAIL_FROM_ADDRESS` | `msp@yourdomain.com` |
   | `MAIL_FROM_NAME` | `MSP Platform` |

   Secrets are passed as runtime env; they are **not** baked into the image.
   Full list: [`.env.production.example`](.env.production.example).

5. Click **Deploy the stack**. The first build on a Pi takes a few minutes.

## Step 3 — Create the first account

Open `http://<pi-ip>:8090`. Because the database is empty, you automatically
see **Create administrator account**. Then the onboarding wizard
(platform name, optional SMTP/Stripe/API token). See
[docs/ONBOARDING.md](docs/ONBOARDING.md).

## Step 4 — (Recommended) Test SMTP

```bash
docker exec -it <app-container> php artisan tinker --execute="Mail::raw('MSP SMTP test', fn (\$m) => \$m->to('you@example.com')->subject('MSP SMTP test'));"
```

Renewal mail (`contracts:send-renewal-reminders`) uses the same `MAIL_*`
config; the queue worker needs the same env (it is in compose).

## Step 5 — (Recommended) Enable Filament 2FA

Authenticator-app MFA (TOTP) is available but **not required**:

1. Sign in at `/admin`.
2. Open **Profile**.
3. Link Google Authenticator / Authy / Microsoft Authenticator (QR code).
4. Store the recovery codes offline.

To require MFA for every panel user, see
[docs/SECRETS.md](docs/SECRETS.md#require-2fa-adminmanager)
(`isRequired: true` in `AdminPanelProvider`). Sanctum API tokens are
unaffected.

## Update after a code change

Push to Git and in Portainer click the stack → **Pull and redeploy**
(or turn on **GitOps updates / webhook** for automatic deploys). The
entrypoint runs migrations again on each start and refreshes assets.

## Data & backups

Everything lives in Docker **named volumes** (they survive redeploys):

- `dbdata` — the PostgreSQL database (customers, contracts, users).
- `storage` — logs, sessions, uploaded files.
- `public` — published assets (refreshed automatically).

Database backup (run on the Pi):

```bash
docker exec <db-container> pg_dump -U msp msp > msp-backup-$(date +%F).sql
```

## HTTPS (recommended)

Put a reverse proxy in front of the stack (Nginx Proxy Manager, Traefik, or
Caddy) that terminates TLS and forwards to port `APP_PORT`. Then set
`SESSION_SECURE_COOKIE=true` and `APP_URL` to your `https://` address, and
redeploy.

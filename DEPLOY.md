# Deploy OpenMSP

Production is **Docker Compose**. The same file works on a VPS, Coolify,
Portainer, or a DigitalOcean droplet:

[`docker-compose.prod.yml`](docker-compose.prod.yml)

It builds the code into the image, migrates on boot, and reads secrets from
environment variables. No bind mounts.

Copy [`.env.production.example`](.env.production.example) and fill it in.
`app`, `queue`, and `scheduler` get **the same** variables. Full notes:
[docs/SECRETS.md](docs/SECRETS.md).

The stack:

| Service | Role |
|---------|------|
| `app` | PHP 8.4-FPM (Laravel). Migrates and caches on start. |
| `web` | Nginx, host port `APP_PORT` (default 8090) |
| `db` | PostgreSQL 16 |
| `queue` | Mail / notifications |
| `scheduler` | Daily reminders (contracts, certificates, planning) |

Health check: `GET /up`.

## Required environment

| Name | Why |
|------|-----|
| `APP_KEY` | Encrypts license keys, 2FA, UI secrets. Generate **once**, never change. |
| `DB_PASSWORD` | PostgreSQL password |
| `APP_URL` | Public URL (mail links, portal, Filament) |
| `APP_PORT` | Host port, default `8090` |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS, else `false` |

Generate `APP_KEY`:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Optional in the same example file: `MAIL_*` (SMTP), `STRIPE_*` (portal
payments), `APP_DEMO_LOGIN`, `APP_TIMEZONE`, locale, log level. SMTP and
Stripe can also be set after first login under **System → Settings**.

## 1) Docker CLI (droplet / any VPS)

```bash
git clone https://github.com/tavbuilds/OpenMSP.git
cd OpenMSP
git checkout main   # or dev
cp .env.production.example .env
# edit .env — at least APP_KEY, DB_PASSWORD, APP_URL
docker compose -f docker-compose.prod.yml up -d --build
```

Open `APP_URL`. Empty database → **Create administrator account**, then
onboarding. See [docs/ONBOARDING.md](docs/ONBOARDING.md).

Updates:

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
```

The entrypoint runs migrations again.

## 2) Coolify

1. New resource → **Docker Compose**.
2. Repository `https://github.com/tavbuilds/OpenMSP`, branch `main` (or `dev`).
3. Compose file: `docker-compose.prod.yml`.
4. Paste the variables from `.env.production.example` into Coolify’s env UI
   (same names; compose interpolates `${APP_KEY}` etc.).
5. Point Coolify’s proxy at the `web` service (container port **80**).
6. Set `APP_URL=https://your.domain` and `SESSION_SECURE_COOKIE=true`.

Coolify already trusts proxies (`TrustProxies` is `*` in the app). Rebuild
on git push.

Do **not** use Nixpacks / “one Dockerfile”. This is a five-service stack.

## 3) Portainer

1. **Stacks → Add stack**.
2. Name e.g. `openmsp`.
3. Build method: **Repository**.
   - URL: your Git clone of OpenMSP
   - Reference: `refs/heads/main`
   - Compose path: `docker-compose.prod.yml`
   - Authentication on if the repo is private
4. Environment variables: the table above, plus optional `MAIL_*` / `STRIPE_*`.
5. Deploy. First build takes a few minutes.

Pull and redeploy (or GitOps webhook) after a push.

## 4) DigitalOcean

**Droplet** (recommended): create a 4 GB droplet, install Docker, then use
§1 or install Coolify on the droplet and use §2.

**App Platform** is a poor fit: this is not a single 12-factor process. You
would need Managed Postgres plus separate workers and durable volumes.
Use a droplet.

## First account

Open the public URL. Because `users` is empty you see **Create administrator
account**. Then the onboarding wizard (platform name, optional SMTP / Stripe /
API token). Registration closes after that.

## HTTPS

Terminate TLS on Caddy, Traefik, Nginx Proxy Manager, or Coolify’s proxy,
forward to `APP_PORT` (or to container port 80). Then:

```env
APP_URL=https://msp.example.com
SESSION_SECURE_COOKIE=true
```

and redeploy.

## Data & backups

Named volumes survive redeploys:

- `dbdata` — PostgreSQL (customers, contracts, users)
- `storage` — logs, sessions, uploads
- `public` — published assets (refreshed on boot)

```bash
docker compose -f docker-compose.prod.yml exec db pg_dump -U msp msp > openmsp-$(date +%F).sql
```

## SMTP test

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker --execute="Mail::raw('OpenMSP SMTP test', fn (\$m) => \$m->to('you@example.com')->subject('OpenMSP SMTP test'));"
```

Reminders (`contracts:send-renewal-reminders`, certificate and planning
commands) use the same `MAIL_*`. The queue worker has those variables too.

## 2FA

Optional TOTP from **Profile**. To require it for every panel user:
**System → Settings**. Sanctum API tokens are unaffected.

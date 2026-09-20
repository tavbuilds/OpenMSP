# Customer portal

Human-facing customer portal at **`/portal`**. Contacts sign in with a
**magic link** (email). This is **not** the Agent API — agents must use
Sanctum tokens and `/api/v1` (see [AGENT.md](../AGENT.md)).

The portal follows the same language rules as admin (browser, then English).

## What customers see

- Active contracts/services for **their company only** (`contact.company_id`)
- Service/license name, current sale price (qty × unit)
- **Yearly**: renewal/expiry date
- **Monthly**: copy that it renews every month (no hard end date)
- Toggle **Enable auto-collect** → Stripe Checkout (iDEAL → SEPA mandate)
- Invoices list + PDF/hosted link (local mirror of Stripe Invoices)
- Optional **renewal / notice emails** to contacts (off per company or per contract in admin)

**Never exposed:** internal cost, margin, license keys.

## Auth

- Model: `Contact` (Authenticatable), guard `portal` (separate from Filament `web`)
- Request link: `POST /portal/login` (throttled)
- Consume: signed URL `GET /portal/magic/{contact}` (30 minutes)
- SMTP must work (`MAIL_*`) — locally `MAIL_MAILER=log` prints the link in `storage/logs`

### Try the magic link locally

```bash
composer update stripe/stripe-php   # once after pulling this branch
cp .env.example .env               # if needed
php artisan key:generate
php artisan migrate
php artisan serve
```

1. In Filament admin: create a Company + Contact with email.
2. Create an **active** Contract for that company.
3. Open `http://localhost:8000/portal/login`, enter the contact email.
4. With `MAIL_MAILER=log`, open `storage/logs/laravel.log` and copy the signed URL.
5. Open the URL → dashboard scoped to that company.

## Stripe (test mode)

### Netherlands collection path (iDEAL → SEPA)

Stripe’s recommended path for recurring collection in the Netherlands:

1. Enable **iDEAL** and **SEPA Direct Debit** in the Stripe Dashboard (test + live).
2. Portal starts a **Checkout Session** with `mode=subscription` and
   `payment_method_types: ['ideal', 'sepa_debit']`.
3. Customer pays/authenticates via **iDEAL**; Stripe creates a reusable
   **SEPA Direct Debit** PaymentMethod + mandate for later invoices.
4. Webhooks mirror invoices and flip `contracts.auto_collect` /
   `stripe_payment_status`.

Quarterly billing maps to Stripe `interval=month`, `interval_count=3`.
One-time (`once`) contracts cannot enable auto-collect.

### Env

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Local webhook forwarding

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

Use the printed `whsec_...` as `STRIPE_WEBHOOK_SECRET`.

Handled events: `checkout.session.completed`, `invoice.paid`,
`invoice.payment_failed`, `customer.subscription.deleted`.

### Test cards / iDEAL

In test mode, Checkout shows test iDEAL banks. Complete the flow; confirm
`auto_collect=true` after `checkout.session.completed`, and an `invoices` row
after `invoice.paid`.

## Admin

- Company view → **Customer portal**: portal users, active collections, Stripe customer ID
- Company → **Contacts** relation: **Portal** column when email is present
- Contract view/edit/table: **Collection** + Stripe payment status (read-only; customers enable it in the portal)
- One-time services (`once`) cannot turn collection on

## Out of scope

MoneyMonk sync, forcing Filament 2FA from this doc, merge to `main`.

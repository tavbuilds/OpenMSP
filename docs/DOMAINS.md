# Domains

A domain is not a contract. It has no quantity, no sale price of its own and
no portal collection — what it has is an expiry date, an auto-renew flag and a
customer. Licences and services stay in the catalog; domains live here.

## Where they come from

Two ways, and they meet in the same table:

- **Openprovider** — **Catalog → Openprovider**, enter your control-panel
  login, Save, Test connection, Sync now. A nightly job keeps it current.
- **By hand** — **Domains → New domain**, for anything registered elsewhere.

A domain you typed in before the first sync is *adopted* by that sync, not
duplicated: matching is on the domain name.

## The customer link is yours

Openprovider knows owner handles, not this platform's companies, so the sync
never writes `company_id`. After an import every domain is unassigned:

1. Open **Domains**.
2. Filter on **Not assigned to a customer**.
3. Select rows → **Assign to customer**.

Later syncs leave those links alone — along with notes and reminder toggles.
Only what the registrar owns (expiry, renewal date, auto-renew, status) is
refreshed.

An unassigned domain appears nowhere in the customer portal.

## Notes

Each domain has one notes field with a **Show this note in the customer
portal** switch. It is **off** by default: a note is internal until you
deliberately share it.

## Reminders

Reminders at 30 / 14 / 7 / 1 days and once on the day it expires, per domain,
**to admins and managers only**. A domain never emails the customer — the
customer hears about renewals through their contract.

A renewal starts a fresh cycle: when the expiry date moves out, the "already
sent" record is cleared, so next year warns again.

## Prices

The sync fetches Openprovider's price per TLD and writes one catalog product
per extension (`.nl domain`, `.com domain`, …), with `renew_price.reseller` as
the cost price. Every domain with that extension points at it, so one price
change lands on all of them.

**Your sale price is yours.** It is seeded once from Openprovider's retail
price and never overwritten again.

## API notes

Base URL `https://api.openprovider.eu`, version path `/v1beta`. Both are
settings under **Catalog → Openprovider → Endpoint**, so a future API version
is a configuration change rather than a code change.

Openprovider ties an API token to a whitelisted IP address: add the server's
IP under **Account → Security** in their control panel, or every call comes
back 401.

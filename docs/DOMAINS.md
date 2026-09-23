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

## Auto-renew

Openprovider answers one of three things per domain: `on`, `off`, or
`default`. The first two are literal. `default` follows a setting on your
Openprovider account that their API does not expose — so OpenMSP stores their
word verbatim and asks you what it means, under **Catalog → Openprovider →
Auto-renew**.

The default here is *renews automatically*, which is the common Openprovider
setup. Change it and every domain that follows the account default is re-read
immediately; no sync needed. Domains that said `on` or `off` are unaffected,
because they never followed the account setting.

The domain page shows both: the effective value at the top, and what the
registrar actually said under **Auto-renew at registrar**.

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

The catalog mirrors your portfolio, not the price call: an extension
Openprovider returns no price for still gets its entry, without a cost price,
and the sync report names it so you can fill it in by hand.

Their `/tlds` filter is a repeated query parameter (`extensions=nl&
extensions=com`), not an indexed array. Sent the usual way, the filter is not
recognised and the answer has nothing to do with the question — which is how
a portfolio of ten extensions once produced two catalog entries.

**Your sale price is yours.** It is seeded once from Openprovider's retail
price and never overwritten again.

## API notes

Base URL `https://api.openprovider.eu`, version path `/v1beta`. Both are
settings under **Catalog → Openprovider → Endpoint**, so a future API version
is a configuration change rather than a code change.

Openprovider ties an API token to a whitelisted IP address: add the server's
IP under **Account → Security** in their control panel, or every call comes
back 401.

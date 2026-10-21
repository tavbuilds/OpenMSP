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

## When a domain leaves

Nothing is ever deleted by a sync. A domain's customer link, its notes and its
reminder history are ours, not the registrar's, and a domain in quarantine can
come back — so a sync that no longer sees a domain *marks* it instead.

A marked domain shows **No longer at the registrar**, drops out of the default
domain list, the dashboard watchlist, the customer's Domains tab and the
customer portal, and stops sending reminders. It keeps everything else. The
**At the registrar** filter on the domain list has three settings — *Current*
(the default), *No longer at the registrar*, and *All* — so nothing is hidden,
only out of the way.

Two guards:

- An empty answer from Openprovider marks nothing. Wrong credentials and a
  closed account look identical from here, and marking the whole portfolio on
  the strength of a blank page would be worse than doing nothing.
- A domain you typed in by hand is never marked. Openprovider was never asked
  about it.

A domain that comes back out of quarantine is unmarked on the next sync. It is
the same row, with the same customer.

This is also the answer to "why is an expired domain still in my list?" — it
is still on the account. Openprovider keeps returning a domain through its
quarantine period, so OpenMSP keeps showing it, with its registrar status in
the **At the registrar** column.

## Two dates

Openprovider publishes both `expiration_date` and `renewal_date`, and its own
specification says of the first that it "is not the primary reference for
domain expiration" and of the second that it is "the date by which a domain
must be renewed to avoid expiration".

So the **Renews** column, the day counter and the reminders all run on the
renewal date, falling back to the expiry date for a domain typed in by hand.
The registry's own expiry is still there, as **Registry expiry** — a hidden
column on the list, always shown on the domain page.

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

A renewal starts a fresh cycle: when either date moves out, the "already sent"
record is cleared, so next year warns again. Either date, because registrars
do not always move both, and a missed reset is a year of silence on a domain
about to lapse.

A domain marked **No longer at the registrar** sends nothing at all.

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

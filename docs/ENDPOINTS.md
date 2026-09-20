# Endpoints / certificates

Not a full CMDB: this is the MSP work queue for **recurring expiry dates**
(your own certificates, optionally domains). Each endpoint has **its own webhook**.

## Create

**Endpoints → New**. Optionally link a customer. Set the expiry by hand, or
let a monitor fill it in.

## Webhook (Uptime Kuma and similar)

The detail page shows a **Webhook URL**. In Uptime Kuma:

1. Notifications → Webhook, content-type **JSON**.
2. URL = this endpoint’s URL (not one global URL).
3. Attach the notification to the HTTPS monitor for that certificate.

The platform reads, among others:

| Field | Example |
|-------|---------|
| `expires_at` / `valid_to` / `expiry` | `2026-12-01` |
| `days_remaining` / `days_until` | `14` (turned into a date) |
| Uptime Kuma `msg` | `… will expire in 14 days` |
| `monitor.url` / `hostname` | fills hostname if it is empty |

Invalid payloads: HTTP 200, `parsed: false`; the payload is stored for debugging.
Unknown token: 404. CSRF is off; rate limit 60/min.

A new expiry date resets the notice cycle (30/14/7/1 again). Rotating the
token invalidates the old URL.

## Notices (per endpoint)

Every morning (08:05 Europe/Amsterdam):

| Toggle | When |
|--------|------|
| 30 / 14 / 7 / 1 days | That threshold, or a catch-up if a day was missed |
| Expired | On the day itself or after, **once** until the date changes |
| Email customer | Extra mail to contacts of the linked customer |

Staff (admin/manager) always get the enabled steps. Turn a toggle off to skip
that step for **this** endpoint.

## Manual

An expiry date on the form is enough if you do not attach a monitor. Source
stays “Manual” until the first webhook arrives.

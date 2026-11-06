# Endpoints / certificates

Not a full CMDB: this is the MSP work queue for **recurring expiry dates**
(your own certificates, optionally domains). Each endpoint has **its own webhook**.

## Create

**Endpoints → New**. Optionally link a customer. Set the expiry by hand, or
let a monitor fill it in.

## Webhook (monitors that post JSON)

The detail page shows a **Webhook URL**. Use it when a monitor posts JSON
itself. Each endpoint has its own URL. The platform reads, among others:

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

## Uptime Kuma

**Catalog → Uptime Kuma** stores the server address, the API key and the
dashboard login.

**Add webhook URLs** (also `push_uptime_kuma_webhooks`, and **Add to Uptime
Kuma** on the endpoint page) creates one JSON webhook per endpoint. It
attaches that webhook to the monitor with the same URL or hostname. When
Kuma has no such monitor, OpenMSP creates an HTTPS check for the endpoint
URL, hostname, or a name that is itself a domain. Certificate and domain
expiry notifications on that monitor are turned off, because OpenMSP sends
the 30/14/7/1 and expired reminders. A downtime channel that was already
on the monitor stays. Two monitors for the same address are left alone.
A domain that fails is tried up to 3 times. The ones that still fail are
marked on the endpoint. **Submit failed domains again** on the endpoints
list and on **Catalog → Uptime Kuma** sends only those. `failed_only: true`
does the same on the API. Select rows on the endpoints list and use
**Submit selection to Uptime Kuma** to send that batch in one go.
`endpoint_ids` does the same on the API. The push then reads
`monitor_cert_days_remaining` and writes that expiry onto the endpoints
it just sent. The nightly job still refreshes the date.
Deleting an endpoint from the list, the endpoint page, or the API also
removes the matching monitor and the OpenMSP webhook from Uptime Kuma.
Two monitors for the same address are left in Kuma and the endpoint stays.
A Kuma error does the same, so the delete can be tried again. Without a
dashboard login the endpoint is still removed here.

The API key only reads `GET /metrics`. The dashboard login is what adds
the webhook. Kuma 2.x is supported. A login with two-factor authentication
is refused.

## Notices (per endpoint)

Every morning (08:05 in `APP_TIMEZONE`, default UTC):

| Toggle | When |
|--------|------|
| 30 / 14 / 7 / 1 days | That threshold, or a catch-up if a day was missed |
| Expired | On the day itself or after, **once** until the date changes |
| Email customer | Extra mail to contacts of the linked customer |

Staff (admin/manager) always get the enabled steps. Turn a toggle off to skip
that step for **this** endpoint.

## Manual

An expiry date on the form is enough if you do not attach a monitor. Source
stays “Manual” until a webhook arrives or the push reads a certificate date.

## Agent API

`/api/v1/endpoints` (see `AGENT.md`). Create returns `webhook_url`. Lists omit
it. `POST /api/v1/endpoints/{id}/rotate-webhook` replaces it.

## Uptime Kuma API key

Store the connection with `PUT /api/v1/integrations/uptime-kuma`
(`base_url`, `api_key`, `username`, `password`, admin/manager). The key
and the password are encrypted and are never returned.
`POST /api/v1/integrations/uptime-kuma/webhooks` adds the webhook URLs and
creates the HTTPS monitor when Kuma does not have one yet.
`POST /api/v1/integrations/uptime-kuma/sync` reads
`monitor_cert_days_remaining` and writes the expiry onto the endpoint with
the same URL or hostname. `create_missing: true` adds an endpoint for a
certificate that does not match yet. The nightly job updates matches and
does not create.

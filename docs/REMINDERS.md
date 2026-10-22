# Reminders

Four things get watched, each on its own schedule: contracts coming up for
renewal, certificates and endpoints expiring, domains expiring, and planned
tasks falling due.

## One mail per kind, not one per thing

Each command collects everything due in that run and sends a single digest.
Six domains expiring on the same morning used to be six separate mails
landing in the same minute, which is the fastest way to teach someone to
ignore all six.

A digest lists every item with its own urgency, sorted soonest-first with
whatever is already past at the top, because it is a worklist. Nothing is
sent when nothing is due — a mail that says all is well is still a mail.

| Command | Goes to |
|---|---|
| `contracts:send-renewal-reminders` | admins and managers; separately, each customer gets a digest of their own contracts where the contract and the company both allow it |
| `endpoints:send-expiry-reminders` | admins and managers; separately, each customer gets their own where **Email customer** is on |
| `domains:send-expiry-reminders` | admins and managers only — a domain never emails the customer |
| `planning:send-deadline-reminders` | admins and managers; an assignee outside those roles gets a digest of the tasks assigned to them, not the whole board |

A customer's digest holds their items and only theirs, so it is built per
company rather than from the whole run.

The notification bell in the panel still carries one entry per contract and
per task. That is a list you click through rather than an inbox you drown
in, so batching it would lose more than it saved.

## Which language

In the language the reader chose, not the server's.

`users.language` and `contacts.language` hold it. The panel's language
picker writes to the signed-in account as well as to the cookie, which is
what makes the choice outlive the browser — a cookie is a fine place for it
right up until something writes to you without a browser in the room, and a
reminder going out at eight in the morning has no cookie to read.

Customers have no picker in the portal, so an admin sets their language on
the contact. Nothing stored means the platform default.

Both models implement `HasLocalePreference`, so Laravel sets each
recipient's locale around `toMail()` and no caller has to think about it.
That is also why `ReminderDigest` builds **every** string inside
`toMail()` and takes models rather than sentences: one digest goes to
several people who may not share a language, and a line built any earlier
would be in whichever language the sender happened to be using.

Dates follow too (`translatedFormat`), so a Dutch reader gets
"29 oktober 2026".

## The reminder cycle

Unchanged by the digests: each item still records which steps it has sent,
sending the 7-day step retires 14 and 30, and a renewal clears the record so
next year warns again. The digest only changes delivery, never which
reminders are due.

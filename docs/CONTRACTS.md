# Contracts

A contract is either **one service** or **a collection of them**.

Most installs start with the first kind — a row for the hosting, a row for
the domain, a row for the certificate — because that is how they were
imported. But a customer did not buy three agreements; they bought
"Website hosting & domains". A collection says so.

## What a collection owns, and what it does not

**The collection decides the term.** Billing cycle, start date, renewal
date, notice period and auto-renew are its own, and they are copied onto
every service inside it. Those fields are locked on a service for that
reason: a service whose dates disagreed with its contract would turn up in
the wrong week's renewal list.

**The services keep their prices.** Each carries its own product, quantity,
cost and sale price, and the collection totals them. It has no price of its
own, deliberately — one number computed from the parts cannot disagree with
them, and two numbers typed in separately always eventually do.

So a collection shows the total sale, the total cost, and the margin across
everything in it, and those feed revenue reporting exactly once.

## Making one

**From services you already have** — the usual case:

1. **Contracts**, tick the rows.
2. **Bulk actions → Combine into one contract.**
3. Name it, set the term, Combine.

Tick an existing collection along with the loose services and they join it
rather than forming a second one.

Leave the renewal date or start date blank and the soonest among the
services is used; leave the notice period blank and the longest is used.
Blank does not mean empty here — the collection's dates overwrite the
services', so an empty one would erase the dates they were running on and
every warning that depends on them.

**From nothing** — when the services do not exist yet:
**Contracts → New contract (collection)**, then **Add service** on the page
it opens.

## Opening one

Clicking a collection opens its page: the totals at the top, then
**Services in this contract** — each with its own price, margin and status,
and actions to edit, take out, or cancel.

## Ending one, or part of one

- **Cancel the collection** → everything inside it is cancelled with it.
- **Cancel one service** → only that one; the rest of the contract runs on.
- **Take a service out** → it leaves the collection and carries on by
  itself, keeping the dates it had. Nothing is cancelled.

The customer can do the first three from the portal. Changing anything
else — a quantity, a price, a term — is an operator's job, because it is a
conversation rather than a button.

### When it actually ends

Written down once, in `ContractCancellation`, so a customer reading the
confirmation and an operator reading the contract are told the same thing.

**Rolling terms (monthly, quarterly)** — cancel whenever. The period that
has started is served, the next one is still invoiced and delivered, and
that invoice is the last. A monthly contract cancelled on 15 October is
billed on 1 November and runs to 30 November.

**Yearly** — notice has to be in before the notice period starts, normally
thirty days. Inside that window the customer cannot do it in the portal any
more: the renewal is too close and somebody has to talk to them. They are
told so, rather than shown a button that fails.

**One-off** — nothing to cancel.

### What happens besides the row

Cancelling a row does not stop the supplier. The licence, the hosting or
the domain behind it renews until a person goes and stops it, so every
cancellation — from the portal or the panel — goes through
`ContractTermination` and:

1. stops the direct debit, if there is one;
2. cancels the contract, and its services if it is a collection;
3. raises a **Cancellation** task, due on the day the service stops,
   naming the services inside it;
4. emails every admin and manager.

## Direct debits and changing a contract

A SEPA mandate is given to us, not to an amount. Changing what a contract
is worth therefore costs the customer nothing: Stripe re-prices the
existing subscription and the authorisation stands.

Nor is there anything to approve. An operator changing a contract in the
panel is carrying out what the customer asked for — that is where the
instruction came from — so the change is made and the subscription
re-priced there and then, taking effect from the next period
(`proration_behavior: none`: no credit, no catch-up charge for a period
already paid).

What the customer *is* owed is the telling. A different sum leaving their
account is not a thing to discover on a bank statement, so every change
that reaches a live mandate is recorded in `contract_billing_changes` and
emailed to them, saying what changed, what the new amount is, and that
their mandate is untouched.

`BillingAmount::changed()` is the one door all of this goes through.
Callers hand it the amount that was being collected *before* whatever
they did (`BillingAmount::collectedBy()`, read first), and it works out
whether anything actually moved. Three things call it:

| Where | What it covers |
|---|---|
| `ContractMoves::into()` / `::out()` | a service joining or leaving a collection |
| `ContractObserver` | a `sale_price` or `quantity` edited in the panel |
| — | nothing else; a rename or a term change costs nothing and says nothing |

The observer is there because editing a quantity used to change what we
invoice without changing what we collect, leaving the subscription on
last month's figure indefinitely. A service inside a collection has no
mandate of its own, so its price feeds the collection's subscription —
that is what `Contract::billingParent()` resolves.

Some moves cost the customer nothing and so say nothing: no mandate in
play, the last service leaving a collecting collection (the subscription
follows it out at the same amount), or a service's own mandate merging
into one that ends up collecting the same total. A mail about an amount
that did not move is noise.

A service inside a collection cannot take a direct debit of its own: two
mandates for one agreement would debit the customer twice. The portal
sends them to the collection instead.

## What changed elsewhere

| Where | Behaviour |
|---|---|
| Contract list | Collections and standalone services. The services inside a collection live on its page. |
| Renewal reminders, watchlists, notice deadlines | The collection, never its services — one agreement, one warning. |
| Dashboard "Active contracts" | A collection counts as one, however many services it holds. |
| Revenue, margin, ARR | Summed over the services. A collection contributes its total exactly once. |
| Customer portal | One line per contract, with a link to the services inside it. |
| Customer key figures, Agent API | Money over `billable()`, counts over `topLevel()` — a collection once. |

## For anyone changing this

The term is **copied down**, not read through. Every renewal query,
reminder and widget already reads `renewal_date` and friends off the
contract row, and read-through would have meant touching all of them and
getting one wrong. `Contract::syncTermToServices()` does the copying, and
the edit page calls it after saving a collection.

Two scopes keep the two readings apart, and using the wrong one is how
double counting gets in:

- `topLevel()` — collections and standalone services. For anything a person
  reads as "a contract": lists, renewals, counts.
- `billable()` — rows that carry money. For sums.

One level only. A collection cannot hold another; `moveIntoGroup()` refuses
it, along with anything belonging to a different customer.

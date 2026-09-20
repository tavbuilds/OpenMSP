<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Billing for NL portal: iDEAL Checkout (subscription mode) establishes
 * a SEPA Direct Debit mandate for recurring collection.
 *
 * Assumption (documented in docs/PORTAL.md):
 * - Checkout Session mode=subscription with payment_method_types=['ideal','sepa_debit']
 * - First payment via iDEAL; Stripe generates a reusable SEPA PM + mandate for renewals
 * - Enable iDEAL and SEPA Direct Debit in the Stripe Dashboard (test + live)
 */
class StripeBillingService
{
    public function client(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new UnexpectedValueException('STRIPE_SECRET is not configured.');
        }

        return new StripeClient($secret);
    }

    public function ensureCustomer(Company $company): string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'name' => $company->name,
            'email' => $company->email,
            'metadata' => [
                'company_id' => (string) $company->id,
            ],
            'address' => array_filter([
                'line1' => $company->address,
                'postal_code' => $company->postal_code,
                'city' => $company->city,
                'country' => $company->country ?: 'NL',
            ]),
        ]);

        $company->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Start automatic collection: hosted Checkout (subscription) with iDEAL → SEPA mandate.
     *
     * @return string Checkout Session URL
     */
    public function createAutoCollectCheckout(Contract $contract, string $successUrl, string $cancelUrl): string
    {
        $contract->loadMissing('company');
        $company = $contract->company;
        if (! $company) {
            throw new UnexpectedValueException('Contract has no company.');
        }

        $customerId = $this->ensureCustomer($company);
        [$interval, $intervalCount] = $this->stripeRecurring($contract->billing_cycle);
        $amountCents = (int) round(((float) $contract->sale_price) * (int) $contract->quantity * 100);

        if ($amountCents < 50) {
            throw new UnexpectedValueException('Amount too low for Stripe (min. €0.50).');
        }

        if ($contract->billing_cycle === BillingCycle::Once) {
            throw new UnexpectedValueException('One-time services cannot use auto-collect subscriptions.');
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'payment_method_types' => ['ideal', 'sepa_debit'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($contract->currency ?: 'eur'),
                    'product_data' => [
                        'name' => $contract->name,
                        'metadata' => [
                            'contract_id' => (string) $contract->id,
                        ],
                    ],
                    'unit_amount' => $amountCents,
                    'recurring' => array_filter([
                        'interval' => $interval,
                        'interval_count' => $intervalCount > 1 ? $intervalCount : null,
                    ]),
                ],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'contract_id' => (string) $contract->id,
                'company_id' => (string) $company->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'contract_id' => (string) $contract->id,
                    'company_id' => (string) $company->id,
                ],
            ],
            'locale' => 'en',
        ]);

        $contract->forceFill([
            'stripe_payment_status' => 'pending',
        ])->save();

        return $session->url;
    }

    public function constructWebhookEvent(string $payload, ?string $signatureHeader): \Stripe\Event
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            throw new UnexpectedValueException('STRIPE_WEBHOOK_SECRET is not configured.');
        }

        return Webhook::constructEvent($payload, $signatureHeader ?? '', $secret);
    }

    public function handleCheckoutSessionCompleted(object $session): void
    {
        $contractId = $session->metadata->contract_id ?? null;
        if (! $contractId) {
            Log::warning('Stripe checkout.session.completed without contract_id metadata');

            return;
        }

        $contract = Contract::find($contractId);
        if (! $contract) {
            return;
        }

        $subscriptionId = is_string($session->subscription ?? null)
            ? $session->subscription
            : ($session->subscription->id ?? null);

        $updates = [
            'auto_collect' => true,
            'auto_collect_enabled_at' => now(),
            'stripe_payment_status' => 'active',
        ];

        if ($subscriptionId) {
            $updates['stripe_subscription_id'] = $subscriptionId;
            try {
                $sub = $this->client()->subscriptions->retrieve($subscriptionId, [
                    'expand' => ['default_payment_method'],
                ]);
                $item = $sub->items->data[0] ?? null;
                if ($item) {
                    $updates['stripe_subscription_item_id'] = $item->id;
                }
                $pm = $sub->default_payment_method;
                if (is_string($pm)) {
                    $updates['stripe_payment_method_id'] = $pm;
                } elseif (is_object($pm) && isset($pm->id)) {
                    $updates['stripe_payment_method_id'] = $pm->id;
                    if (isset($pm->sepa_debit->mandate)) {
                        // mandate may be nested differently; store when present
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Could not expand subscription after checkout', [
                    'error' => $e->getMessage(),
                    'subscription' => $subscriptionId,
                ]);
            }
        }

        $contract->forceFill($updates)->save();
    }

    public function mirrorInvoice(object $stripeInvoice): Invoice
    {
        $customerId = is_string($stripeInvoice->customer ?? null)
            ? $stripeInvoice->customer
            : ($stripeInvoice->customer->id ?? null);

        $company = $customerId
            ? Company::where('stripe_customer_id', $customerId)->first()
            : null;

        $contractId = $stripeInvoice->subscription_details->metadata->contract_id
            ?? $stripeInvoice->metadata->contract_id
            ?? null;

        if (! $contractId && isset($stripeInvoice->lines->data[0]->metadata->contract_id)) {
            $contractId = $stripeInvoice->lines->data[0]->metadata->contract_id;
        }

        $contract = $contractId ? Contract::find($contractId) : null;
        if (! $company && $contract) {
            $company = $contract->company;
        }

        if (! $company) {
            throw new UnexpectedValueException('Cannot mirror invoice: unknown company for '.$stripeInvoice->id);
        }

        $paidAt = null;
        if (($stripeInvoice->status ?? '') === 'paid' && ! empty($stripeInvoice->status_transitions->paid_at)) {
            $paidAt = \Carbon\Carbon::createFromTimestamp($stripeInvoice->status_transitions->paid_at);
        }

        return Invoice::updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoice->id],
            [
                'company_id' => $company->id,
                'contract_id' => $contract?->id,
                'number' => $stripeInvoice->number,
                'amount_due' => (int) ($stripeInvoice->amount_due ?? 0),
                'amount_paid' => (int) ($stripeInvoice->amount_paid ?? 0),
                'currency' => $stripeInvoice->currency ?? 'eur',
                'status' => $stripeInvoice->status ?? 'open',
                'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
                'invoice_pdf' => $stripeInvoice->invoice_pdf,
                'stripe_created_at' => isset($stripeInvoice->created)
                    ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->created)
                    : null,
                'paid_at' => $paidAt,
            ]
        );
    }

    public function markContractPaymentFailed(?string $subscriptionId): void
    {
        if (! $subscriptionId) {
            return;
        }

        Contract::where('stripe_subscription_id', $subscriptionId)
            ->update(['stripe_payment_status' => 'past_due']);
    }

    public function markContractPaymentPaid(?string $subscriptionId): void
    {
        if (! $subscriptionId) {
            return;
        }

        Contract::where('stripe_subscription_id', $subscriptionId)
            ->update(['stripe_payment_status' => 'active']);
    }

    /** Best-effort: stop the Stripe subscription when cancelling in admin. */
    public function cancelSubscription(Contract $contract): void
    {
        if (! filled($contract->stripe_subscription_id)) {
            return;
        }

        try {
            $this->client()->subscriptions->cancel($contract->stripe_subscription_id, [
                'prorate' => false,
            ]);
            $contract->forceFill([
                'auto_collect' => false,
                'stripe_payment_status' => 'cancelled',
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Stripe subscription cancel failed', [
                'contract_id' => $contract->id,
                'subscription' => $contract->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array{0: string, 1: int} */
    protected function stripeRecurring(?BillingCycle $cycle): array
    {
        return match ($cycle) {
            BillingCycle::Monthly => ['month', 1],
            BillingCycle::Quarterly => ['month', 3],
            BillingCycle::Yearly => ['year', 1],
            default => ['year', 1],
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $stripe): Response
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        try {
            $event = $stripe->constructWebhookEvent($payload, $sig);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook config/payload error', ['error' => $e->getMessage()]);

            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $stripe->handleCheckoutSessionCompleted($event->data->object),
                'invoice.paid' => $this->onInvoicePaid($stripe, $event->data->object),
                'invoice.payment_failed' => $this->onInvoiceFailed($stripe, $event->data->object),
                'customer.subscription.deleted' => $this->onSubscriptionDeleted($event->data->object),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler failed', [
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            return response('Handler error', 500);
        }

        return response('ok', 200);
    }

    protected function onInvoicePaid(StripeBillingService $stripe, object $invoice): void
    {
        $stripe->mirrorInvoice($invoice);
        $sub = is_string($invoice->subscription ?? null)
            ? $invoice->subscription
            : ($invoice->subscription->id ?? null);
        $stripe->markContractPaymentPaid($sub);
    }

    protected function onInvoiceFailed(StripeBillingService $stripe, object $invoice): void
    {
        try {
            $stripe->mirrorInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('Could not mirror failed invoice', ['error' => $e->getMessage()]);
        }
        $sub = is_string($invoice->subscription ?? null)
            ? $invoice->subscription
            : ($invoice->subscription->id ?? null);
        $stripe->markContractPaymentFailed($sub);
    }

    protected function onSubscriptionDeleted(object $subscription): void
    {
        \App\Models\Contract::where('stripe_subscription_id', $subscription->id)
            ->update([
                'auto_collect' => false,
                'stripe_payment_status' => 'cancelled',
            ]);
    }
}

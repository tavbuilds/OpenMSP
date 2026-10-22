<?php

namespace App\Support;

use App\Enums\MoveOutcome;
use App\Models\Contract;
use App\Models\ContractBillingChange;
use App\Notifications\BillingChangePending;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Moving a service into a contract, or back out of it.
 *
 * What makes this more than a foreign key is the direct debit. A SEPA
 * mandate is given to us rather than to an amount, so Stripe can re-price
 * the subscription instead of tearing it down — but the sum itself is the
 * customer's to agree to, and agreeing to it is the last step, not an
 * afterthought.
 *
 * So a move that would change what a live mandate collects does not
 * happen. It is written down as a pending change and sent to the customer,
 * and only their approval carries it out. Decline it, or leave it, and the
 * service stays exactly where it was: nothing is added, nothing is
 * removed, and the direct debit goes on collecting what it always did.
 *
 * Moves that cost the customer nothing — no mandate in play, or the same
 * money arranged differently — are simply made.
 */
final class ContractMoves
{
    /** Whether this move changes something the customer is paying by mandate. */
    public static function affectsDirectDebit(Contract $service, ?Contract $group = null): bool
    {
        return $service->collectsByDirectDebit()
            || ($group?->collectsByDirectDebit() ?? false)
            || ($service->parent?->collectsByDirectDebit() ?? false);
    }

    /**
     * What to tell the operator before they confirm a move.
     *
     * @param  iterable<Contract>  $services
     * @return list<string>
     */
    public static function warnings(iterable $services, ?Contract $group = null): array
    {
        $affected = [];

        foreach ($services as $service) {
            if (self::affectsDirectDebit($service, $group)) {
                $affected[] = $service->name;
            }
        }

        if ($affected === []) {
            return [];
        }

        return [
            __('This changes what the direct debit collects for :names, so it is not applied yet: the customer is asked to approve the new amount in the portal first, and nothing moves until they do.', [
                'names' => implode(', ', $affected),
            ]),
        ];
    }

    /** Puts a service into a collection, or asks the customer first. */
    public static function into(Contract $service, Contract $group): MoveOutcome
    {
        if (! $service->canJoinGroup($group)) {
            return MoveOutcome::Refused;
        }

        // Both mandates count: the customer may be paying for the service
        // and for the collection separately today, and what matters is
        // what leaves their account in total.
        $collected = self::collectedBy($group) + self::collectedBy($service);
        $after = $group->collectsByDirectDebit() || $service->collectsByDirectDebit()
            ? $group->periodSalePrice() + $service->periodSalePrice()
            : 0.0;

        if (self::needsConsent($collected, $after)) {
            return self::propose(
                $group,
                $service,
                $collected,
                $after,
                ContractBillingChange::REASON_SERVICE_ADDED,
            );
        }

        return self::performInto($service, $group) ? MoveOutcome::Done : MoveOutcome::Refused;
    }

    /** Takes a service out of its collection, or asks the customer first. */
    public static function out(Contract $service): MoveOutcome
    {
        $group = $service->parent;

        if ($group === null) {
            return MoveOutcome::Refused;
        }

        // What is left behind, plus what the service takes with it: when
        // it was the only one the mandate follows it out, so the total is
        // unchanged and there is nothing to ask.
        $collected = self::collectedBy($group);
        $remaining = $group->periodSalePrice() - $service->periodSalePrice();
        $after = $group->collectsByDirectDebit()
            ? ($group->services()->count() === 1 ? $service->periodSalePrice() : $remaining)
            : 0.0;

        if (self::needsConsent($collected, $after)) {
            return self::propose(
                $group,
                $service,
                $collected,
                $after,
                ContractBillingChange::REASON_SERVICE_REMOVED,
            );
        }

        self::performOut($service, $group);

        return MoveOutcome::Done;
    }

    /**
     * Carries out a move the customer has approved.
     *
     * The amount is checked against the one they said yes to. Prices can
     * be edited while a change waits, and charging a figure nobody agreed
     * to is the thing this whole path exists to prevent.
     */
    public static function apply(ContractBillingChange $change): bool
    {
        $group = $change->contract;
        $service = $change->service;

        if ($group === null || $service === null) {
            return false;
        }

        $adding = $change->isAddition();

        if ($adding ? ! $service->canJoinGroup($group) : ! $service->parent?->is($group)) {
            return false;
        }

        if (abs($change->amountAfterMove() - (float) $change->new_amount) >= 0.01) {
            return false;
        }

        if ($adding) {
            return self::performInto($service, $group);
        }

        self::performOut($service, $group);

        return true;
    }

    // --- Doing the move ------------------------------------------------

    private static function performInto(Contract $service, Contract $group): bool
    {
        $serviceCollected = $service->collectsByDirectDebit();
        $groupCollected = $group->collectsByDirectDebit();

        if (! $service->moveIntoGroup($group)) {
            return false;
        }

        $group->refresh()->load('services');

        if ($groupCollected) {
            // One agreement cannot be debited twice, so a service that
            // brought its own mandate gives it up and the collection's
            // grows to cover it.
            if ($serviceCollected) {
                self::stopCollecting($service->fresh());
            }

            self::reprice($group);

            return true;
        }

        // The collection does not collect but the service did: hand the
        // subscription over rather than throwing the mandate away.
        if ($serviceCollected) {
            self::handOver($service->fresh(), $group);
        }

        return true;
    }

    private static function performOut(Contract $service, Contract $group): void
    {
        $groupCollected = $group->collectsByDirectDebit();
        $wasOnlyService = $group->services()->count() === 1;

        $service->detachFromGroup();

        if (! $groupCollected) {
            return;
        }

        $group->refresh()->load('services');

        // Nothing left in the collection to bill: the mandate follows the
        // service out rather than collecting for an empty shell.
        if ($wasOnlyService) {
            self::handOver($group, $service->fresh());

            return;
        }

        self::reprice($group);
    }

    // --- Asking the customer -------------------------------------------

    /**
     * Writes the move down and sends it to the customer.
     *
     * Nothing is moved and Stripe is not touched. The contract stays as it
     * is, collecting what it always did, until they answer.
     */
    private static function propose(
        Contract $group,
        Contract $service,
        float $collected,
        float $after,
        string $reason,
    ): MoveOutcome {
        // One question at a time. A second move would be measured against
        // a contract the first one is about to change, so both answers
        // together would land on an amount neither of them quoted.
        if ($group->pendingBillingChange() !== null) {
            return MoveOutcome::Blocked;
        }

        $change = $group->billingChanges()->create([
            'company_id' => $group->company_id,
            'service_id' => $service->getKey(),
            'service_name' => $service->name,
            'previous_amount' => $collected,
            'new_amount' => $after,
            'currency' => $group->currency ?: 'EUR',
            'reason' => $reason,
            'status' => 'pending',
        ]);

        self::tellTheCustomer($group, $change);

        return MoveOutcome::AwaitingCustomer;
    }

    private static function needsConsent(float $collected, float $after): bool
    {
        return abs($collected - $after) >= 0.01;
    }

    /** What a contract's own mandate takes today, zero if it has none. */
    private static function collectedBy(Contract $contract): float
    {
        return $contract->collectsByDirectDebit() ? $contract->periodSalePrice() : 0.0;
    }

    // --- Stripe ---------------------------------------------------------

    private static function reprice(Contract $contract): void
    {
        try {
            app(StripeBillingService::class)->updateSubscriptionAmount($contract);
        } catch (Throwable $e) {
            Log::warning('Stripe subscription re-price failed after a move', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Moves a live subscription from one contract to the other. */
    private static function handOver(Contract $from, Contract $to): void
    {
        try {
            app(StripeBillingService::class)->transferSubscription($from, $to->fresh());
        } catch (Throwable $e) {
            Log::warning('Stripe subscription transfer failed', [
                'from' => $from->id,
                'to' => $to->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function stopCollecting(Contract $service): void
    {
        try {
            app(StripeBillingService::class)->cancelSubscription($service);
        } catch (Throwable $e) {
            Log::warning('Stripe subscription cancel failed while moving a service', [
                'contract_id' => $service->id,
                'error' => $e->getMessage(),
            ]);
        }

        $service->forceFill([
            'auto_collect' => false,
            'stripe_payment_status' => null,
            'stripe_subscription_id' => null,
        ])->save();
    }

    private static function tellTheCustomer(Contract $contract, ContractBillingChange $change): void
    {
        $contacts = $contract->company?->contacts()->whereNotNull('email')->get();

        if ($contacts === null || $contacts->isEmpty()) {
            return;
        }

        Notification::send($contacts, new BillingChangePending($change));
    }
}

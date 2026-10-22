<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\ContractBillingChange;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moving a service into a contract, or back out of it.
 *
 * What makes this more than a foreign key is the direct debit. Moving a
 * service changes what the contract collecting the money bills, and the
 * subscription has to follow — a mandate is given to us rather than to
 * an amount, so Stripe can be re-priced without anything being set up
 * again.
 *
 * The operator does not have to ask: a change made here is one the
 * customer asked for. They are told afterwards, which is what
 * BillingAmount does with every change that reaches a live mandate.
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
            __('This changes what the direct debit collects for :names. The subscription is updated from the next period and the customer is emailed about it; nothing is re-authorised and no mandate is lost.', [
                'names' => implode(', ', $affected),
            ]),
        ];
    }

    /** Puts a service into a collection. */
    public static function into(Contract $service, Contract $group): bool
    {
        $name = $service->name;
        $serviceCollected = $service->collectsByDirectDebit();
        $groupCollected = $group->collectsByDirectDebit();

        // Both mandates count, read before the move: the customer may be
        // paying for the service and for the collection separately, and
        // what matters is what leaves their account in total.
        $before = BillingAmount::collectedBy($group) + BillingAmount::collectedBy($service);

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
        } elseif ($serviceCollected) {
            // The collection does not collect but the service did: hand
            // the subscription over rather than throwing the mandate away.
            self::handOver($service->fresh(), $group);
        }

        BillingAmount::changed($group, $before, ContractBillingChange::REASON_SERVICE_ADDED, $service->fresh());

        return true;
    }

    /** Takes a service out of its collection. */
    public static function out(Contract $service): void
    {
        $group = $service->parent;

        if ($group === null) {
            return;
        }

        $groupCollected = $group->collectsByDirectDebit();
        $wasOnlyService = $group->services()->count() === 1;
        $before = BillingAmount::collectedBy($group);

        $service->detachFromGroup();

        if (! $groupCollected) {
            return;
        }

        $group->refresh()->load('services');

        // Nothing left in the collection to bill: the mandate follows the
        // service out rather than collecting for an empty shell. The
        // customer pays what they always did, so there is nothing to say.
        if ($wasOnlyService) {
            self::handOver($group, $service->fresh());

            return;
        }

        BillingAmount::changed($group, $before, ContractBillingChange::REASON_SERVICE_REMOVED, $service->fresh());
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
}

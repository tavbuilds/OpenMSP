<?php

namespace App\Support;

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
 * What makes this more than a foreign key is the direct debit. Moving a
 * service changes what the contract collecting the money bills, and that
 * is not a thing to do quietly.
 *
 * The mandate itself survives: it was given to us, not to an amount, so
 * Stripe can re-price the subscription rather than tear it down and ask
 * the customer to authorise us all over again. What they do have to agree
 * to is the new sum — so the move happens immediately and the change to
 * the collection waits, at the old amount, until they say yes.
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
            __('This changes what the direct debit collects for :names. The current amount keeps running and the customer is asked to approve the new one in the portal; nothing is re-authorised and no mandate is lost.', [
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

        // Read before the move: afterwards the total is the new one, and
        // comparing it with itself would find nothing to ask about.
        $before = self::amountStripeIsCollecting($group);

        if (! $service->moveIntoGroup($group)) {
            return false;
        }

        $group->refresh()->load('services');

        // The collection already collects: its amount simply grows, and
        // a service that had its own mandate stops using it — one
        // agreement cannot be debited twice.
        if ($groupCollected) {
            if ($serviceCollected) {
                self::stopCollecting($service->fresh());
            }

            self::propose($group, $before, $name, ContractBillingChange::REASON_SERVICE_ADDED);

            return true;
        }

        // The collection does not collect but the service did: hand the
        // subscription over rather than throwing the mandate away.
        if ($serviceCollected) {
            $collected = self::amountStripeIsCollecting($service->fresh());
            self::handOver($service->fresh(), $group, $name, $collected);
        }

        return true;
    }

    /** Takes a service out of its collection. */
    public static function out(Contract $service): void
    {
        $group = $service->parent;
        $name = $service->name;
        $groupCollected = $group?->collectsByDirectDebit() ?? false;
        $wasOnlyService = $group !== null && $group->services()->count() === 1;
        $before = $group !== null ? self::amountStripeIsCollecting($group) : 0.0;

        $service->detachFromGroup();

        if (! $groupCollected || $group === null) {
            return;
        }

        $group->refresh()->load('services');

        // Nothing left in the collection to bill: the mandate follows the
        // service out rather than collecting for an empty shell.
        if ($wasOnlyService) {
            self::handOver($group, $service->fresh(), $name, $before);

            return;
        }

        self::propose($group, $before, $name, ContractBillingChange::REASON_SERVICE_REMOVED);
    }

    /**
     * Records the new amount and asks the customer for it.
     *
     * Stripe is not touched here. The old amount keeps being collected,
     * which is the only amount anyone has agreed to, until the customer
     * answers in the portal.
     */
    private static function propose(Contract $contract, float $previous, string $serviceName, string $reason): void
    {
        $new = (float) $contract->total_sale;

        // One question at a time, and the old one goes first even when
        // this move asks nothing new: a service taken out and put back
        // would otherwise leave the customer looking at a drop that has
        // already been undone.
        self::dropTheOldQuestion($contract);

        if (abs($previous - $new) < 0.01) {
            return;
        }

        $change = $contract->billingChanges()->create([
            'company_id' => $contract->company_id,
            'service_name' => $serviceName,
            'previous_amount' => $previous,
            'new_amount' => $new,
            'currency' => $contract->currency ?: 'EUR',
            'reason' => $reason,
            'status' => 'pending',
        ]);

        self::tellTheCustomer($contract, $change);
    }

    /**
     * Retires a question the customer has not answered.
     *
     * Not a decline: they never said no, and a decline is what stops the
     * mandate. The row stays for the record, out of the way.
     */
    private static function dropTheOldQuestion(Contract $contract): void
    {
        $contract->billingChanges()->pending()->update(['status' => 'superseded']);
    }

    /**
     * What the subscription is set to collect right now.
     *
     * Not the contract's current total: an earlier change may be waiting,
     * in which case Stripe is still on the amount from before that one,
     * and asking about the difference from anything else would misstate
     * what the customer is actually paying.
     */
    private static function amountStripeIsCollecting(Contract $contract): float
    {
        $pending = $contract->billingChanges()->pending()->latest('id')->first();

        return (float) ($pending?->previous_amount ?? $contract->total_sale);
    }

    /**
     * Moves a live subscription from one contract to the other.
     *
     * $collected is what Stripe is taking today, read before the move:
     * the contract it is leaving may be empty by now, and its total would
     * say the customer is paying nothing.
     */
    private static function handOver(Contract $from, Contract $to, string $serviceName, float $collected): void
    {
        try {
            app(StripeBillingService::class)->transferSubscription($from, $to->fresh());
        } catch (Throwable $e) {
            Log::warning('Stripe subscription transfer failed', [
                'from' => $from->id,
                'to' => $to->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $to->refresh();

        self::dropTheOldQuestion($from);
        self::dropTheOldQuestion($to);

        if (abs($collected - (float) $to->total_sale) < 0.01) {
            return;
        }

        $change = $to->billingChanges()->create([
            'company_id' => $to->company_id,
            'service_name' => $serviceName,
            'previous_amount' => $collected,
            'new_amount' => (float) $to->total_sale,
            'currency' => $to->currency ?: 'EUR',
            'reason' => ContractBillingChange::REASON_SERVICE_ADDED,
            'status' => 'pending',
        ]);

        self::tellTheCustomer($to, $change);
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

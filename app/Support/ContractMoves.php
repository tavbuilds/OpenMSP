<?php

namespace App\Support;

use App\Models\Contract;
use App\Notifications\DirectDebitStopped;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Moving a service into a collection, or back out of it.
 *
 * The complication is the direct debit. A SEPA mandate is given for one
 * agreement; when the service it covers becomes part of a larger contract,
 * or leaves one to stand alone, the thing being billed is not the thing
 * the customer authorised any more. The mandate is stopped and the
 * customer is told, because the next thing they notice would otherwise be
 * an invoice they expected to be collected automatically.
 */
final class ContractMoves
{
    /** Whether this move will cost the customer a direct debit. */
    public static function stopsDirectDebit(Contract $service): bool
    {
        return (bool) $service->auto_collect || filled($service->stripe_subscription_id);
    }

    /**
     * What to warn the operator about before they confirm a move.
     *
     * @param  iterable<Contract>  $services
     * @return list<string>
     */
    public static function warnings(iterable $services): array
    {
        $affected = [];

        foreach ($services as $service) {
            if (self::stopsDirectDebit($service)) {
                $affected[] = $service->name;
            }
        }

        if ($affected === []) {
            return [];
        }

        return [
            trans_choice(
                '{1} The direct debit for :names stops, and the customer is emailed about it. They give a new mandate for the contract it is moving to.|[2,*] The direct debits for :names stop, and the customer is emailed about it. They give a new mandate for the contract they are moving to.',
                count($affected),
                ['names' => implode(', ', $affected)],
            ),
        ];
    }

    /** Puts a service into a collection, stopping its own debit first. */
    public static function into(Contract $service, Contract $group): bool
    {
        $stopped = self::stopDirectDebit($service);

        if (! $service->moveIntoGroup($group)) {
            return false;
        }

        if ($stopped) {
            self::tellTheCustomer($service, $group);
        }

        return true;
    }

    /** Takes a service out, stopping the collection's debit for its part. */
    public static function out(Contract $service): void
    {
        $stopped = self::stopDirectDebit($service);

        $service->detachFromGroup();

        if ($stopped) {
            self::tellTheCustomer($service, null);
        }
    }

    private static function stopDirectDebit(Contract $service): bool
    {
        if (! self::stopsDirectDebit($service)) {
            return false;
        }

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
            'stripe_payment_status' => 'cancelled',
        ])->save();

        return true;
    }

    private static function tellTheCustomer(Contract $service, ?Contract $nowBilledBy): void
    {
        $contacts = $service->company?->contacts()->whereNotNull('email')->get();

        if ($contacts === null || $contacts->isEmpty()) {
            return;
        }

        Notification::send($contacts, new DirectDebitStopped($service, $nowBilledBy));
    }
}

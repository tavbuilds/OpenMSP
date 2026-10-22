<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\ContractBillingChange;
use App\Notifications\BillingAmountChanged;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Keeping the direct debit on the amount the contract is worth.
 *
 * An operator changing a contract is carrying out what the customer
 * asked for, so there is nothing to approve: the change is made and the
 * subscription re-priced there and then. The mandate is untouched by
 * that — it was given to us, not to an amount.
 *
 * What the customer is owed is to be told. A different sum leaving their
 * account is not a thing to discover on a bank statement, so every
 * change that reaches a live mandate is recorded here and mailed to
 * them.
 */
final class BillingAmount
{
    /**
     * What a contract's own mandate takes today, zero if it has none.
     *
     * Read before whatever is about to change it, because afterwards
     * there is nothing left to compare against.
     */
    public static function collectedBy(?Contract $contract): float
    {
        if ($contract === null || ! $contract->collectsByDirectDebit()) {
            return 0.0;
        }

        return $contract->periodSalePrice();
    }

    /**
     * Brings the subscription to the contract's new amount and says so.
     *
     * Does nothing when the figure has not moved — a term edit, a rename,
     * a service added to a contract nobody collects for — so callers can
     * hand it every change without sorting out which ones matter.
     */
    public static function changed(
        Contract $contract,
        float $previous,
        string $reason,
        ?Contract $service = null,
    ): void {
        $contract = $contract->fresh();

        if ($contract === null) {
            return;
        }

        $now = self::collectedBy($contract);

        if (abs($previous - $now) < 0.01) {
            return;
        }

        self::reprice($contract);

        $change = $contract->billingChanges()->create([
            'company_id' => $contract->company_id,
            'service_id' => $service?->getKey(),
            'service_name' => $service?->name,
            'previous_amount' => $previous,
            'new_amount' => $now,
            'currency' => $contract->currency ?: 'EUR',
            'reason' => $reason,
        ]);

        self::tellTheCustomer($contract, $change);
    }

    private static function reprice(Contract $contract): void
    {
        try {
            app(StripeBillingService::class)->updateSubscriptionAmount($contract);
        } catch (Throwable $e) {
            Log::warning('Stripe subscription re-price failed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function tellTheCustomer(Contract $contract, ContractBillingChange $change): void
    {
        $contacts = $contract->company?->contacts()->whereNotNull('email')->get();

        if ($contacts === null || $contacts->isEmpty()) {
            return;
        }

        Notification::send($contacts, new BillingAmountChanged($change));
        $change->forceFill(['notified_at' => now()])->save();
    }
}

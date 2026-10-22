<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\ContractBillingChange;
use App\Support\BillingAmount;

/**
 * Keeps a live direct debit on the price the contract actually carries.
 *
 * Editing a quantity or a sale price in the panel used to change what we
 * invoice without changing what we collect, so the subscription quietly
 * went on taking last month's amount. An operator making that edit is
 * acting on the customer's instruction, so it needs no approval — it
 * needs to reach Stripe, and the customer needs to hear about it.
 *
 * Only money is watched here. Moving a service between contracts is
 * ContractMoves' business and would otherwise be handled twice.
 */
class ContractObserver
{
    /** @var list<string> */
    private const MONEY = ['sale_price', 'quantity'];

    public function updating(Contract $contract): void
    {
        if (! $contract->isDirty(self::MONEY)) {
            return;
        }

        // Read the old amount now, because once this is saved there is
        // nothing left to compare against.
        $contract->collectedBeforeSave = $contract->isInGroup()
            // A collection prices itself from its services as the
            // database holds them, and this row is not written yet.
            ? BillingAmount::collectedBy($contract->parent?->fresh())
            // This row is the one that collects, and it is already
            // carrying the new values, so go back to the originals.
            : self::amountBeforeTheEdit($contract);
    }

    private static function amountBeforeTheEdit(Contract $contract): float
    {
        if (! $contract->collectsByDirectDebit()) {
            return 0.0;
        }

        return (float) $contract->getOriginal('sale_price') * (int) $contract->getOriginal('quantity');
    }

    public function updated(Contract $contract): void
    {
        $before = $contract->collectedBeforeSave;
        $contract->collectedBeforeSave = null;

        if ($before === null) {
            return;
        }

        $collector = $contract->billingParent();

        if ($collector === null) {
            return;
        }

        BillingAmount::changed(
            $collector,
            $before,
            ContractBillingChange::REASON_AMOUNT_EDITED,
            $contract,
        );
    }
}

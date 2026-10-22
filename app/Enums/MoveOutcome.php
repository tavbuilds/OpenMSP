<?php

namespace App\Enums;

/**
 * What came of asking to move a service in or out of a contract.
 *
 * A move that changes what a live direct debit collects is not ours to
 * make: it waits for the customer. The caller has to be able to tell the
 * operator which of those happened, because "done" and "asked" look the
 * same from the outside and only one of them changed anything.
 */
enum MoveOutcome: string
{
    /** Carried out. */
    case Done = 'done';

    /** Recorded and sent to the customer; nothing has moved yet. */
    case AwaitingCustomer = 'awaiting_customer';

    /** Another change is already waiting on the customer for this contract. */
    case Blocked = 'blocked';

    /** Not a move that is allowed at all. */
    case Refused = 'refused';

    public function changedSomething(): bool
    {
        return $this === self::Done;
    }
}

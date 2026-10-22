<?php

namespace App\Support;

use App\Enums\BillingCycle;
use App\Models\Contract;
use Carbon\CarbonInterface;

/**
 * What happens, and when, if this contract is cancelled today.
 *
 * Written down in one place because a customer pressing "cancel" in the
 * portal and an operator looking at the same contract have to be told the
 * same dates, and because getting them wrong means invoicing somebody for a
 * service we stopped providing, or the reverse.
 *
 * Two rules, by term:
 *
 *  - **Rolling (monthly, quarterly)** — cancel whenever. The period that
 *    has already started is served and the next one is still invoiced and
 *    delivered; that invoice is the last. A month cancelled on the 15th of
 *    October is billed on 1 November and runs to the end of November.
 *  - **Yearly** — notice has to be in before the notice period starts,
 *    which is normally thirty days. Inside that window the customer cannot
 *    do it themselves any more; the renewal is too close and somebody has
 *    to talk to them.
 */
final class ContractCancellation
{
    public function __construct(private Contract $contract) {}

    public static function for(Contract $contract): self
    {
        return new self($contract);
    }

    /** Terms that end on their renewal date rather than rolling on. */
    private function isFixedTerm(): bool
    {
        return $this->contract->billing_cycle === BillingCycle::Yearly;
    }

    /** A one-off has nothing to cancel: it is already over when it is over. */
    public function isCancellable(): bool
    {
        return $this->contract->canCancel()
            && $this->contract->billing_cycle !== BillingCycle::Once;
    }

    /**
     * Whether the customer may do it themselves, right now.
     *
     * On a yearly term, inside the notice window, they may not: the
     * renewal is already committed and someone has to agree what happens.
     */
    public function customerMayCancel(): bool
    {
        if (! $this->isCancellable()) {
            return false;
        }

        return ! $this->isInsideNoticeWindow();
    }

    /** The renewal is close enough that notice can no longer be given. */
    public function isInsideNoticeWindow(): bool
    {
        if (! $this->isFixedTerm()) {
            return false;
        }

        $deadline = $this->noticeDeadline();

        return $deadline !== null && $deadline->isPast();
    }

    /** Last day notice can be given to stop the next renewal. */
    public function noticeDeadline(): ?CarbonInterface
    {
        if (! $this->contract->renewal_date) {
            return null;
        }

        return $this->contract->renewal_date->copy()
            ->subDays((int) $this->contract->notice_period_days)
            ->startOfDay();
    }

    /**
     * The last invoice the customer will receive.
     *
     * Rolling: the next one due. Fixed term: none — the year they are in
     * has been paid for, and cancelling stops the renewal rather than the
     * current period.
     */
    public function lastInvoiceOn(): ?CarbonInterface
    {
        if ($this->isFixedTerm()) {
            return null;
        }

        return $this->nextBillingDate();
    }

    /**
     * The day the service actually stops.
     *
     * Rolling: the end of the period the last invoice pays for. Fixed
     * term: the renewal date, which is when it would otherwise roll over.
     */
    public function endsOn(): ?CarbonInterface
    {
        if ($this->isFixedTerm()) {
            return $this->contract->renewal_date?->copy()->startOfDay();
        }

        $lastInvoice = $this->lastInvoiceOn();
        $cycle = $this->contract->billing_cycle;

        if ($lastInvoice === null || ! $cycle instanceof BillingCycle) {
            return null;
        }

        // The invoice pays for the period that starts on its own date, so
        // the service runs to the day before the one after it.
        return $cycle->addPeriod($lastInvoice)->subDay()->startOfDay();
    }

    /**
     * The next date this contract bills on, counting forward from whatever
     * anchor it has until it lands in the future.
     */
    private function nextBillingDate(): ?CarbonInterface
    {
        $cycle = $this->contract->billing_cycle;

        if (! $cycle instanceof BillingCycle || $cycle === BillingCycle::Once) {
            return null;
        }

        $date = $this->contract->next_invoice_date
            ?? $this->contract->renewal_date
            ?? $this->contract->start_date;

        if ($date === null) {
            return null;
        }

        $date = $date->copy()->startOfDay();
        $today = now()->startOfDay();

        // A guard, not a loop bound: a corrupt anchor must not spin here.
        for ($i = 0; $i < 400 && $date->lte($today); $i++) {
            $date = $cycle->addPeriod($date);
        }

        return $date;
    }

    /** What to put in front of the customer before they confirm. */
    public function summaryForCustomer(): string
    {
        if ($this->isInsideNoticeWindow()) {
            return __('This contract renews on :renewal and the notice period of :days days has already started. Contact support and we will sort it out with you.', [
                'renewal' => $this->contract->renewal_date?->translatedFormat('j F Y'),
                'days' => (int) $this->contract->notice_period_days,
            ]);
        }

        if ($this->isFixedTerm()) {
            return __('This cancels the renewal on :renewal. You keep the service until then, and there is nothing further to pay.', [
                'renewal' => $this->contract->renewal_date?->translatedFormat('j F Y'),
            ]);
        }

        $lastInvoice = $this->lastInvoiceOn();
        $ends = $this->endsOn();

        if ($lastInvoice === null || $ends === null) {
            return __('We will confirm the final date with you by email.');
        }

        return __('Your last invoice is on :invoice and the service runs until :ends. Nothing is billed after that.', [
            'invoice' => $lastInvoice->translatedFormat('j F Y'),
            'ends' => $ends->translatedFormat('j F Y'),
        ]);
    }
}

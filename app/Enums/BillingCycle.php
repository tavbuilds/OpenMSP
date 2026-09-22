<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case Once = 'once';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
            self::Once => 'One-time',
        };
    }

    /**
     * Cycles whose renewal is a date you have to act before.
     *
     * Monthly is excluded because the customer can cancel at any month
     * boundary, so its renewal is not an event anyone plans around; one-time
     * work never renews at all, and its date is an end date. What is left is
     * the committed terms, where missing the notice deadline costs a year (or
     * a quarter) of a contract nobody wanted to extend.
     *
     * @return list<self>
     */
    public static function withRenewalTerm(): array
    {
        return array_values(array_filter(self::cases(), fn (self $cycle): bool => $cycle->hasRenewalTerm()));
    }

    /** @return list<string> */
    public static function renewalTermValues(): array
    {
        return array_map(fn (self $cycle): string => $cycle->value, self::withRenewalTerm());
    }

    /** Whether this cycle renews on a term worth watching ahead of time. */
    public function hasRenewalTerm(): bool
    {
        return match ($this) {
            self::Quarterly, self::Yearly => true,
            self::Monthly, self::Once => false,
        };
    }

    /** How many times per year this cycle is billed (for MRR/ARR). */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Quarterly => 4,
            self::Yearly => 1,
            self::Once => 0,
        };
    }

    public function addPeriod(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3),
            self::Yearly => $date->copy()->addYearNoOverflow(),
            self::Once => $date->copy(),
        };
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * One money format for every human-facing screen.
 *
 * The portal, the admin pages and the dashboard stats each used to format
 * amounts their own way ("€ 80.00" next to "EUR 80.00" next to "€80.00"),
 * so they all go through here. Filament's own `money()` columns format with
 * the same `Number::currency()`, which keeps tables and everything around
 * them in step.
 */
class Money
{
    private const FALLBACK_CURRENCY = 'EUR';

    /**
     * Format an amount given in major units (e.g. 80.0 euros).
     *
     * `$precision` is for unit prices that need more than two decimals;
     * leave it null for the currency's own default.
     */
    public static function format(
        float|int|string|null $amount,
        ?string $currency = self::FALLBACK_CURRENCY,
        ?int $precision = null,
    ): string {
        return Number::currency((float) $amount, self::currency($currency), precision: $precision);
    }

    /** Format an amount given in minor units (e.g. 8000 cents), as Stripe reports them. */
    public static function formatMinor(int|float|null $amount, ?string $currency = self::FALLBACK_CURRENCY): string
    {
        return self::format(((float) $amount) / 100, $currency);
    }

    /** Format without decimals, for headline figures such as the dashboard stats. */
    public static function formatWhole(float|int|string|null $amount, ?string $currency = self::FALLBACK_CURRENCY): string
    {
        return Number::currency((float) $amount, self::currency($currency), precision: 0);
    }

    private static function currency(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));

        return $code !== '' ? $code : self::FALLBACK_CURRENCY;
    }
}

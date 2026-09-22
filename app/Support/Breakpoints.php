<?php

namespace App\Support;

/**
 * Responsive Filament schema grids.
 * Integer columns() only kick in at `lg` (~1024px); tablets need `md`.
 */
final class Breakpoints
{
    /** @var array<string, int> */
    public const TWO = [
        'default' => 1,
        'md' => 2,
    ];

    /** @var array<string, int> */
    public const THREE = [
        'default' => 1,
        'md' => 2,
        'lg' => 3,
    ];

    /** @var array<string, int> */
    public const FOUR = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    /*
     * Table columns: `visibleFrom()` measures the viewport, but the panel's
     * sidebar and page padding eat roughly 384px of it, so a column that
     * needs "lg" of table width only gets it on an "xl" viewport. Pick the
     * tier by how important the column is; the offset lives here.
     */

    /** Shown from ~640px of table width (tablet and up). */
    public const COLUMN_SECONDARY = 'lg';

    /** Shown from ~900px of table width (laptop and up). */
    public const COLUMN_TERTIARY = 'xl';

    /** Shown from ~1150px of table width (wide screens only). */
    public const COLUMN_WIDEST = '2xl';
}

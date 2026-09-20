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
}

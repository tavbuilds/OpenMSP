<?php

namespace App\Filament\Concerns;

/**
 * Puts a page or resource in the sidebar group under its translated name.
 *
 * Filament groups by the string it is given, so a page that skips this
 * does not join the group — it starts a second one beside it, and the
 * sidebar shows "Systeem" and "System" as if they were different places.
 * One missed override is all it takes, which is why this is shared
 * rather than copied.
 */
trait TranslatesNavigationGroup
{
    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return is_string(static::$navigationGroup)
            ? __(static::$navigationGroup)
            : parent::getNavigationGroup();
    }
}

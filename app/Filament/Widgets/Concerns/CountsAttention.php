<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A dashboard watchlist: one query feeds both the table and the count on the
 * tab that hides it. Keeping them apart is how the two drift.
 */
trait CountsAttention
{
    /** Rows this watchlist is currently pointing at. */
    abstract public static function attentionQuery(): Builder;

    public static function attentionCount(): int
    {
        return static::attentionQuery()->count();
    }

    protected function getTableQuery(): Builder
    {
        return static::attentionQuery();
    }
}

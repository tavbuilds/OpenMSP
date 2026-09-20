<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait AuthorizesByRole
{
    public static function canCreate(): bool
    {
        return auth()->user()?->canManageContracts() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageContracts() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }
}

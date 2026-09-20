<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Manager => __('Manager'),
            self::Sales => __('Sales'),
            self::Viewer => __('Read only'),
        };
    }

    /** Rollen die contracts mogen aanmaken/wijzigen. */
    public function canManage(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Sales], true);
    }

    /** Rollen die mogen verwijderen en users/instellingen beheren. */
    public function canAdminister(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }
}

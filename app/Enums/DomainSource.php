<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DomainSource: string implements HasLabel
{
    case Manual = 'manual';
    case OpenProvider = 'openprovider';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::OpenProvider => __('Openprovider'),
        };
    }
}

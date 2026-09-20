<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EndpointKind: string implements HasLabel, HasColor
{
    case Certificate = 'certificate';
    case Domain = 'domain';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Certificate => 'Certificate',
            self::Domain => 'Domain',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Certificate => 'info',
            self::Domain => 'warning',
            self::Other => 'gray',
        };
    }
}

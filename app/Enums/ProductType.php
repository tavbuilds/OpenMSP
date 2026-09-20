<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel, HasColor
{
    case License = 'license';
    case Support = 'support';
    case Subscription = 'subscription';
    case Service = 'service';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::License => 'License',
            self::Support => 'Support / maintenance',
            self::Subscription => 'Subscription',
            self::Service => 'Service',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::License => 'info',
            self::Support => 'warning',
            self::Subscription => 'success',
            self::Service => 'primary',
            self::Other => 'gray',
        };
    }
}

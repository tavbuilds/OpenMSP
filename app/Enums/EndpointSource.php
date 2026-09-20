<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EndpointSource: string implements HasLabel
{
    case Manual = 'manual';
    case Webhook = 'webhook';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Webhook => 'Webhook',
        };
    }
}

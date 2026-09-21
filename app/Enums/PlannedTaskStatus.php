<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlannedTaskStatus: string implements HasLabel, HasColor
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::InProgress => __('In progress'),
            self::Blocked => __('Blocked'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'info',
            self::Blocked => 'danger',
            self::Done => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Done, self::Cancelled => false,
            default => true,
        };
    }
}

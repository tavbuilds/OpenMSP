<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PlannedTaskKind: string implements HasLabel, HasColor
{
    case Relocation = 'relocation';
    case Migration = 'migration';
    case Onsite = 'onsite';
    case Project = 'project';
    // Raised when a customer cancels: somebody has to stop the licence,
    // the hosting or the domain from renewing, and a mail alone sinks.
    case Cancellation = 'cancellation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Relocation => __('Relocation'),
            self::Migration => __('Migration'),
            self::Onsite => __('On-site'),
            self::Project => __('Project'),
            self::Cancellation => __('Cancellation'),
            self::Other => __('Other'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Relocation => 'warning',
            self::Migration => 'info',
            self::Onsite => 'success',
            self::Project => 'primary',
            self::Cancellation => 'danger',
            self::Other => 'gray',
        };
    }
}

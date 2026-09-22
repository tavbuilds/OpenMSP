<?php

namespace App\Filament\Pages;

use App\Support\DemoData as DemoDataService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class DemoData extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Demo data';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'demodata';

    protected static ?string $title = 'Demo data';

    protected string $view = 'filament.pages.demo-data';

    public static function getNavigationLabel(): string
    {
        return __('Demo data');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Demo data');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return is_string(static::$navigationGroup)
            ? __(static::$navigationGroup)
            : parent::getNavigationGroup();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public function loaded(): bool
    {
        return DemoDataService::exists();
    }

    public function seedAction(): Action
    {
        return Action::make('seed')
            ->label(__('Load demo data'))
            ->icon('heroicon-o-plus')
            ->disabled(fn (): bool => $this->loaded())
            ->requiresConfirmation()
            ->modalHeading(__('Load the sample portfolio?'))
            ->modalDescription(__('Adds recognizable sample customers, contracts, and catalog items. Everything is flagged as demo and can be deleted in one click.'))
            ->action(function (): void {
                DemoDataService::seed();
                Notification::make()->title(__('Demo data loaded'))->success()->send();
            });
    }

    public function purgeAction(): Action
    {
        return Action::make('purge')
            ->label(__('Delete all demo data'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->disabled(fn (): bool => ! $this->loaded())
            ->requiresConfirmation()
            ->modalHeading(__('Delete all demo data?'))
            ->modalDescription(__('Removes every record with the demo flag: customers, contacts, contracts, products, vendors, and purchase bundles. Real data stays.'))
            ->action(function (): void {
                $n = DemoDataService::purge();
                Notification::make()->title("{$n} demo record(s) deleted")->success()->send();
            });
    }
}

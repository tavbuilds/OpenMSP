<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesNavigationGroup;
use App\Support\DemoData as DemoDataService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class DemoData extends Page
{
    use TranslatesNavigationGroup;

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

    /**
     * Gone for good once the demo data has been deleted.
     *
     * Filament asks this for the sidebar and again for the route, so the
     * page does not merely disappear from the menu — the URL stops
     * answering too, which is the half that matters once someone has
     * said they are done with demo records.
     */
    public static function canAccess(): bool
    {
        if (! DemoDataService::stillOffered()) {
            return false;
        }

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
            ->modalDescription(__('Removes every record with the demo flag: customers, contacts, contracts, products, vendors, and purchase bundles. Real data stays. This page then disappears — run php artisan demo:seed if you ever want it back.'))
            ->action(function (): void {
                $n = DemoDataService::purge();

                Notification::make()
                    ->title(trans_choice('{0} No demo records to delete|{1} 1 demo record deleted|[2,*] :count demo records deleted', $n, ['count' => $n]))
                    ->body(__('Demo data is off the menu now.'))
                    ->success()
                    ->send();

                // The page it was triggered from no longer exists.
                $this->redirect(Dashboard::getUrl());
            });
    }
}

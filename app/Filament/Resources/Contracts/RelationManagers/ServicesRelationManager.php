<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Enums\ContractStatus;
use App\Enums\MoveOutcome;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Support\Breakpoints;
use App\Support\ContractMoves;
use App\Support\ContractTermination;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * What is actually inside a collection.
 *
 * Each row is a service with its own product and price; the dates are the
 * collection's and are not editable here, which is the whole point of
 * putting them together.
 */
class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Services in this contract');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contract && $ownerRecord->isGroup();
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->recordUrl(fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('No services yet'))
            ->emptyStateDescription(__('Add one, or combine existing contracts into this one from the contract list.'))
            ->columns([
                TextColumn::make('name')->label(__('Service'))->weight('bold')->searchable(),
                TextColumn::make('product.name')->label(__('Catalog item'))->placeholder(__('—'))->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('quantity')->label(__('Qty'))->alignEnd(),
                TextColumn::make('total_sale')->label(__('Sale'))->money('EUR')->alignEnd(),
                TextColumn::make('margin_eur')
                    ->label(__('Margin €'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add service'))
                    ->url(fn () => ContractResource::getUrl('create', ['parent' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                EditAction::make()->url(fn (Contract $record) => ContractResource::getUrl('edit', ['record' => $record])),
                Action::make('detach')
                    ->label(__('Take out'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('Take this service out of the contract?'))
                    ->modalDescription(fn (Contract $record): string => implode(' ', [
                        __('It carries on by itself, keeping the dates it has now. Nothing is cancelled.'),
                        ...ContractMoves::warnings([$record]),
                    ]))
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->action(function (Contract $record): void {
                        self::reportMove(ContractMoves::out($record), $record->name);
                    }),
                Action::make('cancelService')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Cancel this service?'))
                    ->modalDescription(__('Only this one. The rest of the contract carries on.'))
                    ->visible(fn (Contract $record): bool => ! $this->isReadOnly() && $record->canCancel())
                    ->action(function (Contract $record): void {
                        // Through the same path as a customer cancelling,
                        // so the task and the staff mail happen either way.
                        ContractTermination::cancel($record, by: 'admin');

                        Notification::make()->title(__('Service cancelled'))->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('detachMany')
                        ->label(__('Take out of this contract'))
                        ->icon('heroicon-o-arrow-up-tray')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Collection $records): string => implode(' ', [
                            __('They carry on by themselves, keeping the dates they have now. Nothing is cancelled.'),
                            ...ContractMoves::warnings($records),
                        ]))
                        ->action(function (Collection $records): void {
                            $moved = [];
                            $asked = [];
                            $stuck = [];

                            foreach ($records as $record) {
                                match (ContractMoves::out($record)) {
                                    MoveOutcome::Done => $moved[] = $record->name,
                                    MoveOutcome::AwaitingCustomer => $asked[] = $record->name,
                                    default => $stuck[] = $record->name,
                                };
                            }

                            self::reportBulkMove($moved, $asked, $stuck);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Says what actually happened, which is not always the move.
     *
     * A move that changes what a live direct debit collects is only
     * proposed here; the customer carries it out. Reporting that as done
     * would have the operator believing a service had left a contract it
     * is still in.
     */
    private static function reportMove(MoveOutcome $outcome, string $name): void
    {
        match ($outcome) {
            MoveOutcome::Done => Notification::make()
                ->title(__('Taken out of the contract'))
                ->body(__(':name runs on its own now.', ['name' => $name]))
                ->success()
                ->send(),
            MoveOutcome::AwaitingCustomer => Notification::make()
                ->title(__('Waiting on the customer'))
                ->body(__('This changes their direct debit, so :name stays where it is until they approve it in the portal.', ['name' => $name]))
                ->warning()
                ->send(),
            MoveOutcome::Blocked => Notification::make()
                ->title(__('Another change is already waiting'))
                ->body(__('The customer still has to answer the last one. Withdraw it on the contract page, or wait for their answer.'))
                ->danger()
                ->send(),
            MoveOutcome::Refused => Notification::make()
                ->title(__('Could not take it out'))
                ->body(__(':name is not part of this contract.', ['name' => $name]))
                ->danger()
                ->send(),
        };
    }

    /**
     * @param  list<string>  $moved
     * @param  list<string>  $asked
     * @param  list<string>  $stuck
     */
    private static function reportBulkMove(array $moved, array $asked, array $stuck): void
    {
        if ($moved !== []) {
            Notification::make()
                ->title(__('Taken out of the contract'))
                ->body(trans_choice('{1} :count service runs on its own now.|[2,*] :count services run on their own now.', count($moved), ['count' => count($moved)]))
                ->success()
                ->send();
        }

        if ($asked !== []) {
            Notification::make()
                ->title(__('Waiting on the customer'))
                ->body(__('These change their direct debit, so they stay where they are until approved: :names.', ['names' => implode(', ', $asked)]))
                ->warning()
                ->send();
        }

        if ($stuck !== []) {
            Notification::make()
                ->title(__('Not taken out'))
                ->body(__('Nothing happened for :names. A change may already be waiting on the customer.', ['names' => implode(', ', $stuck)]))
                ->danger()
                ->send();
        }
    }

    /** The collection's own figures, under the table. */
    public function totalSale(): string
    {
        $group = $this->getOwnerRecord();

        return Money::format($group->periodSalePrice(), $group->currency);
    }

    public function activeServiceCount(): int
    {
        return $this->getOwnerRecord()->services()
            ->where('status', ContractStatus::Active->value)
            ->count();
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FailedCollections extends BaseWidget
{
    protected static ?string $heading = 'Failed collections';

    public function getHeading(): ?string
    {
        return __('Failed collections');
    }

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Contract::query()->where('stripe_payment_status', 'past_due')->exists();
    }

    protected function getTableQuery(): Builder
    {
        return Contract::query()
            ->with('company')
            ->where('stripe_payment_status', 'past_due')
            ->orderByDesc('updated_at');
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('company.name')->label(__('Customer'))->weight('bold'),
            TextColumn::make('name')->label(__('Service')),
            TextColumn::make('stripe_payment_status')
                ->label(__('Status'))
                ->badge()
                ->formatStateUsing(fn (Contract $record) => $record->stripePaymentStatusLabel())
                ->color(fn (Contract $record) => $record->stripePaymentStatusColor()),
            TextColumn::make('total_sale')->label(__('Amount'))->money('EUR')->alignEnd()->visibleFrom('md'),
            TextColumn::make('updated_at')->label(__('Last updated'))->since()->visibleFrom('lg'),
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Widgets\Concerns\CountsAttention;
use App\Models\Domain;
use App\Support\Breakpoints;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingDomainExpiries extends BaseWidget
{
    use CountsAttention;

    public function getTableHeading(): ?string
    {
        return __('Domains (60 days or expired)');
    }

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function attentionQuery(): Builder
    {
        return Domain::query()
            ->with(['company', 'product'])
            ->expiringWithin(60)
            ->orderBy('expires_at');
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (Domain $record) => DomainResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('Domain name'))->weight('bold'),
            TextColumn::make('company.name')
                ->label(__('Customer'))
                ->placeholder(__('Not assigned'))
                ->visibleFrom('md'),
            TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y'),
            TextColumn::make('days')
                ->label(__('Days'))
                ->state(fn (Domain $record) => $record->daysUntilExpiry())
                ->badge()
                ->color(fn (Domain $record) => $record->statusColor()),
            IconColumn::make('auto_renew')
                ->label(__('Auto-renew'))
                ->boolean()
                ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Endpoints\EndpointResource;
use App\Filament\Widgets\Concerns\CountsAttention;
use App\Models\Endpoint;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingEndpointExpiries extends BaseWidget
{
    use CountsAttention;

    public function getTableHeading(): ?string
    {
        return __('Endpoints / certificates (60 days or expired)');
    }

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Endpoint::query()->whereNotNull('expires_at')->exists();
    }

    public static function attentionQuery(): Builder
    {
        return Endpoint::query()
            ->with('company')
            ->whereNotNull('expires_at')
            ->where(function (Builder $q): void {
                $q->whereDate('expires_at', '<=', now()->addDays(60)->toDateString());
            })
            ->orderBy('expires_at');
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (Endpoint $record) => EndpointResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')->label(__('Endpoint'))->weight('bold'),
            TextColumn::make('company.name')->label(__('Customer'))->placeholder(__('Internal'))->visibleFrom('md'),
            TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom('lg'),
            TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y'),
            TextColumn::make('days')
                ->label(__('Days'))
                ->state(fn (Endpoint $record) => $record->daysUntilExpiry())
                ->badge()
                ->color(fn (Endpoint $record) => $record->statusColor()),
            TextColumn::make('source')->label(__('Source'))->badge()->visibleFrom('xl'),
        ];
    }
}

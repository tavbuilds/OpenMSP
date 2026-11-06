<?php

namespace App\Filament\Resources\Endpoints\Tables;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Filament\Resources\Endpoints\EndpointResource;
use App\Models\Endpoint;
use App\Models\User;
use App\Monitoring\UptimeKuma\UptimeKumaException;
use App\Monitoring\UptimeKuma\UptimeKumaWebhooks;
use App\Support\Breakpoints;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class EndpointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at', 'asc')
            ->recordUrl(fn (Endpoint $record) => EndpointResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('company.name')->label(__('Customer'))->placeholder(__('Internal'))->searchable()->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('kind')->label(__('Type'))->badge()->visibleFrom(Breakpoints::COLUMN_TERTIARY),
                TextColumn::make('hostname')->label(__('Host'))->placeholder(__('—'))->toggleable()->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('expires_at')->label(__('Expires'))->date('M j, Y')->sortable()->placeholder(__('—')),
                TextColumn::make('days')
                    ->label(__('Days'))
                    ->state(fn (Endpoint $record) => $record->daysUntilExpiry())
                    ->badge()
                    ->color(fn (Endpoint $record) => $record->statusColor()),
                TextColumn::make('last_status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (Endpoint $record) => $record->statusLabel())
                    ->color(fn (Endpoint $record) => $record->statusColor())
                    ->visibleFrom(Breakpoints::COLUMN_SECONDARY),
                TextColumn::make('source')->label(__('Source'))->badge()->visibleFrom(Breakpoints::COLUMN_WIDEST),
                TextColumn::make('kuma_push_error')
                    ->label(__('Kuma push'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('Failed') : '—')
                    ->color(fn (?string $state): string => filled($state) ? 'danger' : 'gray')
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('Type'))->options(EndpointKind::class),
                SelectFilter::make('source')->label(__('Source'))->options(EndpointSource::class),
                SelectFilter::make('company_id')->label(__('Customer'))->relationship('company', 'name')->searchable()->preload(),
                TernaryFilter::make('kuma_failed')
                    ->label(__('Kuma push failed'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('kuma_push_failed_at'),
                        false: fn ($query) => $query->whereNull('kuma_push_failed_at'),
                    ),
                TernaryFilter::make('expired')
                    ->label(__('Expired'))
                    ->queries(
                        true: fn ($q) => $q->whereDate('expires_at', '<', now()->toDateString()),
                        false: fn ($q) => $q->whereDate('expires_at', '>=', now()->toDateString()),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('pushKuma')
                    ->label(__('Submit selection to Uptime Kuma'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Submit the selected domains to Uptime Kuma?'))
                    ->modalDescription(__('OpenMSP sends only the selected domains. Each domain is tried up to 3 times.'))
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->canAdminister())
                    ->action(function (Collection $records): void {
                        abort_unless(auth()->user() instanceof User && auth()->user()->canAdminister(), 403);

                        try {
                            $report = app(UptimeKumaWebhooks::class)->push(endpointIds: $records
                                ->map(fn (Endpoint $endpoint): int => (int) $endpoint->getKey())
                                ->all());
                        } catch (UptimeKumaException $e) {
                            Notification::make()
                                ->title(__('Could not add the webhook URLs'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($report['endpoints'] === 0) {
                            Notification::make()
                                ->title(__('No domains are waiting for another try.'))
                                ->info()
                                ->send();

                            return;
                        }

                        $notification = Notification::make()
                            ->title(__('Webhook URLs sent to Uptime Kuma'))
                            ->body(UptimeKumaWebhooks::summary($report));
                        ($report['failed_count'] > 0 ? $notification->warning() : $notification->success())->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

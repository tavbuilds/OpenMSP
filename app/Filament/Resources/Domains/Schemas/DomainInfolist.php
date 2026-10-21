<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Models\Domain;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DomainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // One line at the top, only when it applies: without it a
                // removed domain reads like any other, just quieter.
                TextEntry::make('removed_notice')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->visible(fn (Domain $record) => $record->isRemoved())
                    ->state(fn (Domain $record) => __('Openprovider stopped returning this domain on :date. It is kept here with its customer, notes and history, and no longer sends reminders.', [
                        'date' => $record->removed_at?->isoFormat('LL'),
                    ])),

                Section::make(__('Key figures'))
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('due')
                            ->label(__('Renews'))
                            ->state(fn (Domain $record) => $record->expiryDate())
                            ->date('M j, Y')
                            ->placeholder(__('—')),
                        TextEntry::make('days')
                            ->label(__('Days'))
                            ->state(fn (Domain $record) => $record->daysUntilExpiry() ?? __('—'))
                            ->badge()
                            ->color(fn (Domain $record) => $record->statusColor())
                            ->helperText(fn (Domain $record) => $record->statusLabel()),
                        TextEntry::make('auto_renew')
                            ->label(__('Auto-renew'))
                            ->badge()
                            ->state(fn (Domain $record) => $record->autoRenewLabel())
                            ->color(fn (Domain $record) => $record->auto_renew ? 'success' : 'gray'),
                        TextEntry::make('cost')
                            ->label(__('Purchase price / year'))
                            ->state(fn (Domain $record) => $record->costPrice() !== null
                                ? Money::format($record->costPrice(), $record->product?->currency)
                                : __('—'))
                            ->helperText(fn (Domain $record) => $record->product?->name),
                    ]),

                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Details'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('name')->label(__('Domain name'))->copyable(),
                                TextEntry::make('company.name')->label(__('Customer'))->placeholder(__('Not assigned')),
                                TextEntry::make('extension')->label(__('Extension'))->placeholder(__('—')),
                                TextEntry::make('status')
                                    ->label(__('Registrar status'))
                                    ->badge()
                                    ->placeholder(__('—'))
                                    ->color(fn (Domain $record) => $record->registrarStatusColor())
                                    ->formatStateUsing(fn (Domain $record) => $record->registrarStatusLabel()),
                                TextEntry::make('renewal_date')
                                    ->label(__('Renewal date'))
                                    ->date('M j, Y')
                                    ->placeholder(__('—'))
                                    ->helperText(__('The date Openprovider wants the renewal by — what the countdown and the reminders use.')),
                                TextEntry::make('expires_at')
                                    ->label(__('Registry expiry'))
                                    ->date('M j, Y')
                                    ->placeholder(__('—')),
                                TextEntry::make('auto_renew_source')
                                    ->label(__('Auto-renew at registrar'))
                                    ->placeholder(__('—'))
                                    ->formatStateUsing(fn (?string $state) => match ($state) {
                                        'on' => __('On'),
                                        'off' => __('Off'),
                                        'default' => __('Account default'),
                                        default => $state,
                                    }),
                                TextEntry::make('source')->label(__('Source'))->badge(),
                                TextEntry::make('last_synced_at')
                                    ->label(__('Last synced'))
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder(__('Never')),
                                TextEntry::make('removed_at')
                                    ->label(__('No longer at the registrar since'))
                                    ->dateTime('M j, Y H:i')
                                    ->visible(fn (Domain $record) => $record->isRemoved()),
                            ]),

                        Tab::make(__('Notes'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                TextEntry::make('notes')
                                    ->hiddenLabel()
                                    ->placeholder(__('—'))
                                    ->columnSpanFull(),
                                TextEntry::make('notes_visible_to_customer')
                                    ->label(__('Shown in the customer portal'))
                                    ->badge()
                                    ->state(fn (Domain $record) => $record->notes_visible_to_customer ? __('Yes') : __('No'))
                                    ->color(fn (Domain $record) => $record->notes_visible_to_customer ? 'warning' : 'gray'),
                            ]),

                        Tab::make(__('Reminders'))
                            ->icon(Heroicon::OutlinedBell)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('reminder_audience')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->state(__('Internal only — a domain never emails the customer. Renewal mail to customers comes from their contract.')),
                                IconEntry::make('notify_30')->label(__('30 days before'))->boolean(),
                                IconEntry::make('notify_14')->label(__('14 days before'))->boolean(),
                                IconEntry::make('notify_7')->label(__('7 days before'))->boolean(),
                                IconEntry::make('notify_1')->label(__('1 day before'))->boolean(),
                                IconEntry::make('notify_expired')->label(__('When it has expired'))->boolean(),
                            ]),
                    ]),
            ]);
    }
}

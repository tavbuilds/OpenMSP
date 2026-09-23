<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Enums\DomainSource;
use App\Models\Domain;
use App\Support\Breakpoints;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Domain'))
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Domain name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder('customer.nl')
                            ->helperText(fn (?Domain $record) => $record?->source?->value === 'openprovider'
                                ? __('Kept in step with Openprovider — the next sync overwrites a change here.')
                                : null)
                            ->columnSpanFull(),

                        Select::make('company_id')
                            ->label(__('Customer'))
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder(__('Not assigned'))
                            ->helperText(__('Assigned here, not at the registrar. A sync never changes it.')),

                        DatePicker::make('expires_at')
                            ->label(__('Expires')),

                        Toggle::make('auto_renew')
                            ->label(__('Auto-renew'))
                            ->inline(false)
                            // The registrar owns this for a synced domain, via
                            // its own value plus the account default.
                            ->disabled(fn (?Domain $record) => $record?->source === DomainSource::OpenProvider)
                            ->helperText(fn (?Domain $record) => $record?->source === DomainSource::OpenProvider
                                ? __('Set at Openprovider: :value', ['value' => $record->autoRenewLabel()])
                                : null),
                    ]),

                Section::make(__('Notes'))
                    ->description(__('Visible to the customer only when you say so. Off by default.'))
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('notes_visible_to_customer')
                            ->label(__('Show this note in the customer portal'))
                            ->default(false),
                    ]),

                Section::make(__('Reminders'))
                    ->description(__('Internal only — a domain never emails the customer. Renewal mail to customers comes from their contract.'))
                    ->columns(Breakpoints::THREE)
                    ->collapsed()
                    ->schema([
                        Toggle::make('notify_30')->label(__('30 days before'))->default(true),
                        Toggle::make('notify_14')->label(__('14 days before'))->default(true),
                        Toggle::make('notify_7')->label(__('7 days before'))->default(true),
                        Toggle::make('notify_1')->label(__('1 day before'))->default(true),
                        Toggle::make('notify_expired')->label(__('When it has expired'))->default(true),
                    ]),
            ]);
    }
}

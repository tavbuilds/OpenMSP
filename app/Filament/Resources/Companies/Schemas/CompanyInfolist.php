<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\ContractStatus;
use App\Models\Company;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;

class CompanyInfolist
{
    /**
     * Money first, then everything else behind a tab.
     *
     * The contacts list used to appear three times on this page — its own
     * card, the portal card, and the Contacts tab below. Only the relation
     * manager is left, since that is the one you can edit from.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Key figures'))
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('active_contracts_count')
                            ->label(__('Active contracts'))
                            ->state(fn (Company $record) => $record->contracts()
                                ->where('status', ContractStatus::Active->value)->count()),

                        TextEntry::make('mrr')
                            ->label(__('MRR'))
                            ->state(fn (Company $record) => Money::format($record->mrr())),

                        TextEntry::make('arr')
                            ->label(__('ARR'))
                            ->state(fn (Company $record) => Money::format($record->mrr() * 12)),

                        TextEntry::make('annual_margin')
                            ->label(__('Annual margin'))
                            ->state(fn (Company $record) => Money::format($record->annualMargin()))
                            ->color('success'),
                    ]),

                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Details'))
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('name')->label(__('Company name')),
                                TextEntry::make('email')->label(__('Email'))->placeholder(__('—'))->copyable(),
                                TextEntry::make('phone')->label(__('Phone'))->placeholder(__('—')),
                                TextEntry::make('kvk_number')->label(__('Company registration'))->placeholder(__('—')),
                                TextEntry::make('vat_number')->label(__('VAT number'))->placeholder(__('—')),
                                TextEntry::make('country')->label(__('Country'))->placeholder(__('—')),
                                TextEntry::make('address')->label(__('Address'))->placeholder(__('—')),
                                TextEntry::make('postal_code')->label(__('Postal code'))->placeholder(__('—')),
                                TextEntry::make('city')->label(__('City'))->placeholder(__('—')),
                                TextEntry::make('notes')
                                    ->label(__('Notes'))
                                    ->placeholder(__('—'))
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('Customer portal'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('portal_hint')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->state(__('Contacts with email sign in at /portal via magic link. They enable collection themselves (iDEAL → SEPA).')),

                                TextEntry::make('notify_renewals')
                                    ->label(__('Customer renewal email'))
                                    ->state(fn (Company $record) => $record->notify_renewals ? __('On') : __('Off'))
                                    ->badge()
                                    ->color(fn (Company $record) => $record->notify_renewals ? 'success' : 'gray'),

                                TextEntry::make('portal_contacts_count')
                                    ->label(__('Portal users'))
                                    ->state(fn (Company $record) => $record->contacts
                                        ->filter(fn ($c) => $c->canUsePortal())
                                        ->count())
                                    ->helperText(fn (Company $record) => $record->contacts
                                        ->contains(fn ($c) => $c->canUsePortal())
                                        ? null
                                        : __('No contact with email — add an email address under Contacts.')),

                                TextEntry::make('auto_collect_count')
                                    ->label(__('Collection active'))
                                    ->state(fn (Company $record) => $record->contracts()
                                        ->where('auto_collect', true)
                                        ->count()),

                                TextEntry::make('stripe_customer_id')
                                    ->label(__('Stripe customer'))
                                    ->placeholder(__('None yet (created on first collection)'))
                                    ->copyable()
                                    ->fontFamily(FontFamily::Mono)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

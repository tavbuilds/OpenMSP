<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\ContractStatus;
use App\Models\Company;
use App\Support\Breakpoints;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Key figures')
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

                Section::make('Company details')
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
                    ]),

                Section::make('Contacts')
                    ->schema([
                        TextEntry::make('contacts')
                            ->hiddenLabel()
                            ->state(function (Company $record) {
                                $contacts = $record->contacts;
                                if ($contacts->isEmpty()) {
                                    return '—';
                                }

                                return $contacts->map(function ($c) {
                                    $line = trim(
                                        $c->name
                                        .($c->job_title ? " ({$c->job_title})" : '')
                                        .($c->email ? " · {$c->email}" : '')
                                        .($c->phone ? " · {$c->phone}" : '')
                                    );

                                    if ($c->canUsePortal()) {
                                        $line .= ' · portal';
                                    }

                                    return $line;
                                })->implode("\n");
                            }),
                    ]),

                Section::make('Customer portal')
                    ->description(__('Contacts with email sign in at /portal via magic link. They enable collection themselves (iDEAL → SEPA).'))
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextEntry::make('notify_renewals')
                            ->label(__('Customer renewal email'))
                            ->state(fn (Company $record) => $record->notify_renewals ? 'On' : 'Off')
                            ->badge()
                            ->color(fn (Company $record) => $record->notify_renewals ? 'success' : 'gray'),
                        TextEntry::make('portal_contacts_count')
                            ->label(__('Portal users'))
                            ->state(fn (Company $record) => $record->contacts
                                ->filter(fn ($c) => $c->canUsePortal())
                                ->count()),

                        TextEntry::make('auto_collect_count')
                            ->label(__('Collection active'))
                            ->state(fn (Company $record) => $record->contracts()
                                ->where('auto_collect', true)
                                ->count()),

                        TextEntry::make('stripe_customer_id')
                            ->label(__('Stripe customer'))
                            ->placeholder(__('None yet (created on first collection)'))
                            ->copyable()
                            ->fontFamily(FontFamily::Mono),

                        TextEntry::make('portal_contacts')
                            ->label(__('Can sign in'))
                            ->columnSpanFull()
                            ->state(function (Company $record) {
                                $portal = $record->contacts->filter(fn ($c) => $c->canUsePortal());
                                if ($portal->isEmpty()) {
                                    return 'No contact with email — add an email address under Contacts.';
                                }

                                return $portal->map(fn ($c) => $c->name.' · '.$c->email)->implode("\n");
                            }),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')->hiddenLabel()->placeholder(__('—'))->columnSpanFull(),
                    ]),
            ]);
    }
}

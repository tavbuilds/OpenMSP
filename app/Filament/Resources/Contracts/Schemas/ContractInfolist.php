<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Support\Breakpoints;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ContractInfolist
{
    /**
     * What the contract is worth, then the detail behind tabs.
     *
     * Twenty-odd fields in five stacked cards gave the margin — the number
     * this page exists for — exactly as much weight as the Stripe
     * subscription ID.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Key figures'))
                    ->columns(Breakpoints::FOUR)
                    ->schema([
                        TextEntry::make('total_sale')->label(__('Total sale'))->money('EUR'),
                        TextEntry::make('margin_eur')
                            ->label(__('Margin €'))
                            ->money('EUR')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        TextEntry::make('margin_pct')
                            ->label(__('Margin %'))
                            ->suffix('%')
                            ->badge()
                            ->color(fn ($state) => $state >= 30 ? 'success' : ($state >= 15 ? 'warning' : 'danger')),
                        TextEntry::make('renewal_date')
                            ->label(__('Renewal / end date'))
                            ->date('M j, Y')
                            ->placeholder(__('—')),
                    ]),

                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Details'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('company.name')->label(__('Company')),
                                TextEntry::make('vendor.name')->label(__('Vendor'))->placeholder(__('—')),
                                TextEntry::make('name')->label(__('Service / package')),
                                TextEntry::make('type')->label(__('Type'))->badge(),
                                TextEntry::make('reference')->label(__('Reference'))->placeholder(__('—')),
                                TextEntry::make('status')->label(__('Status'))->badge(),
                            ]),

                        Tab::make(__('Term & renewal'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('start_date')->label(__('Start date'))->date('M j, Y'),
                                TextEntry::make('renewal_date')->label(__('Renewal / end date'))->date('M j, Y')->placeholder(__('—')),
                                TextEntry::make('notice_deadline')->label(__('Notice deadline'))->date('M j, Y')->placeholder(__('—')),
                                TextEntry::make('notice_period_days')->label(__('Notice period (days)')),
                                IconEntry::make('auto_renew')->label(__('Auto-renew'))->boolean(),
                                TextEntry::make('next_invoice_date')->label(__('Next invoice'))->date('M j, Y')->placeholder(__('—')),
                                IconEntry::make('notify_renewals')->label(__('Customer email for this license'))->boolean(),
                            ]),

                        Tab::make(__('Price & quantity'))
                            ->icon(Heroicon::OutlinedCurrencyEuro)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('quantity')->label(__('Quantity')),
                                TextEntry::make('sale_price')->label(__('Sale / unit'))->money('EUR'),
                                TextEntry::make('effective_cost')
                                    ->label(__('Effective cost'))
                                    ->money('EUR')
                                    ->helperText(fn ($record) => $record->uses_bundle ? __('Allocated share of the bundle') : null),
                                TextEntry::make('billing_cycle')->label(__('Billing cycle'))->badge(),
                                TextEntry::make('currency')->label(__('Currency')),
                                TextEntry::make('purchaseBundle.name')
                                    ->label(__('Purchase bundle'))
                                    ->placeholder(__('— (fixed cost)'))
                                    ->badge()
                                    ->color('info'),
                            ]),

                        Tab::make(__('Auto-collect'))
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->columns(Breakpoints::THREE)
                            ->schema([
                                TextEntry::make('auto_collect_hint')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->state(fn ($record) => $record->canEnableAutoCollect()
                                        ? __('Customers enable collection in the portal (iDEAL → SEPA). Admin is read-only.')
                                        : __('One-time service — no auto-collect.')),

                                IconEntry::make('auto_collect')
                                    ->label(__('Collection'))
                                    ->boolean(),
                                TextEntry::make('stripe_payment_status')
                                    ->label(__('Stripe status'))
                                    ->badge()
                                    ->placeholder(__('Off'))
                                    ->formatStateUsing(fn ($record) => $record->stripePaymentStatusLabel())
                                    ->color(fn ($record) => $record->stripePaymentStatusColor()),
                                TextEntry::make('auto_collect_enabled_at')
                                    ->label(__('Enabled at'))
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder(__('—')),
                                TextEntry::make('stripe_subscription_id')
                                    ->label(__('Stripe subscription'))
                                    ->placeholder(__('—'))
                                    ->copyable()
                                    ->fontFamily(FontFamily::Mono)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('License keys & notes'))
                            ->icon(Heroicon::OutlinedKey)
                            ->schema([
                                TextEntry::make('license_keys')
                                    ->label(__('License keys'))
                                    // Visible only to roles that manage contracts.
                                    ->visible(fn () => Auth::user()?->canManageContracts() ?? false)
                                    ->placeholder(__('—'))
                                    ->columnSpanFull(),
                                TextEntry::make('notes')->label(__('Notes'))->placeholder(__('—'))->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

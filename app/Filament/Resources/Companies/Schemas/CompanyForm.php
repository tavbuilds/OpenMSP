<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\Company;
use App\Support\Breakpoints;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    /**
     * Ten fields in one flat grid read as a wall. Grouped, only the first
     * group is actually required to save a customer.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Customer'))
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Company name'))
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('kvk_number')
                            ->label(__('Company registration')),
                        TextInput::make('vat_number')
                            ->label(__('VAT number')),
                    ]),

                Section::make(__('Contact details'))
                    ->columns(Breakpoints::THREE)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('Email address'))
                            ->email(),
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel(),
                        TextInput::make('address')
                            ->label(__('Address')),
                        TextInput::make('postal_code')
                            ->label(__('Postal code')),
                        TextInput::make('city')
                            ->label(__('City')),
                        TextInput::make('country')
                            ->label(__('Country'))
                            ->required()
                            ->default('NL'),
                    ]),

                Section::make(__('Renewal reminders'))
                    ->schema([
                        Toggle::make('notify_renewals')
                            ->label(__('Customer email on renewal / notice'))
                            ->default(true)
                            ->helperText(__('Off = no reminder email to this customer’s contacts, regardless of the license.')),
                    ]),

                Section::make(__('Notes'))
                    // Folded away on a new customer; open when there is
                    // something in it.
                    ->collapsed(fn (?Company $record) => blank($record?->notes))
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

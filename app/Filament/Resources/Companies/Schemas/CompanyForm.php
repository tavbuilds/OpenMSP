<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Support\Breakpoints;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(Breakpoints::TWO)
            ->components([
                TextInput::make('name')
                    ->label(__('Company name'))
                    ->required(),
                TextInput::make('kvk_number')
                    ->label(__('Company registration')),
                TextInput::make('vat_number')
                    ->label(__('VAT number')),
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
                Toggle::make('notify_renewals')
                    ->label(__('Customer email on renewal / notice'))
                    ->default(true)
                    ->helperText(__('Off = no reminder email to this customer’s contacts, regardless of the license.'))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Endpoints\Schemas;

use App\Enums\EndpointKind;
use App\Support\Breakpoints;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EndpointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Endpoint')
                ->columns(Breakpoints::TWO)
                ->schema([
                    Select::make('company_id')
                        ->label(__('Customer'))
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder(__('Internal (no customer)')),
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(120)
                        ->helperText(__('e.g. VPN certificate or mail.customer.com')),
                    Select::make('kind')
                        ->label(__('Type'))
                        ->options(EndpointKind::class)
                        ->default(EndpointKind::Certificate->value)
                        ->required(),
                    TextInput::make('hostname')
                        ->label(__('Hostname'))
                        ->placeholder(__('vpn.customer.nl')),
                    TextInput::make('url')
                        ->label(__('URL'))
                        ->url()
                        ->placeholder(__('https://vpn.customer.nl')),
                    DatePicker::make('expires_at')
                        ->label(__('Expires'))
                        ->helperText(__('Set by hand, or filled automatically from this endpoint’s webhook (Uptime Kuma, etc.).')),
                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->columnSpanFull(),
                ]),
            Section::make('Notifications')
                ->description(__('Staff (admin/manager) receive the enabled steps. Each toggle is per endpoint.'))
                ->columns(Breakpoints::TWO)
                ->schema([
                    Toggle::make('notify_30')->label(__('30 days before'))->default(true),
                    Toggle::make('notify_14')->label(__('14 days before'))->default(true),
                    Toggle::make('notify_7')->label(__('7 days before'))->default(true),
                    Toggle::make('notify_1')->label(__('1 day before'))->default(true),
                    Toggle::make('notify_expired')->label(__('When it has expired'))->default(true),
                    Toggle::make('notify_customer')
                        ->label(__('Also email customer contacts'))
                        ->helperText(__('Only if this endpoint is linked to a customer that has contacts with email.')),
                ]),
        ]);
    }
}

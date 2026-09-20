<?php

namespace App\Filament\Resources\Endpoints\Schemas;

use App\Models\Endpoint;
use App\Support\Breakpoints;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class EndpointInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Endpoint')
                ->columns(Breakpoints::THREE)
                ->schema([
                    TextEntry::make('name')->label(__('Name'))->weight('bold'),
                    TextEntry::make('company.name')->label(__('Customer'))->placeholder(__('Internal')),
                    TextEntry::make('kind')->label(__('Type'))->badge(),
                    TextEntry::make('hostname')->label(__('Hostname'))->placeholder(__('—'))->copyable(),
                    TextEntry::make('url')->label(__('URL'))->placeholder(__('—'))->url(fn ($state) => $state),
                    TextEntry::make('expires_at')->label(__('Expires'))->date('M j, Y')->placeholder(__('Unknown')),
                    TextEntry::make('days')
                        ->label(__('Days'))
                        ->state(function (Endpoint $record): string {
                            $d = $record->daysUntilExpiry();
                            if ($d === null) {
                                return '—';
                            }

                            return $d < 0 ? 'expired ('.abs($d).' d)' : (string) $d;
                        }),
                    TextEntry::make('last_status')
                        ->label(__('Status'))
                        ->badge()
                        ->formatStateUsing(fn (Endpoint $record) => $record->statusLabel())
                        ->color(fn (Endpoint $record) => $record->statusColor()),
                    TextEntry::make('source')->label(__('Source'))->badge(),
                ]),
            Section::make('Webhook')
                ->description(__('Paste this URL into Uptime Kuma (Notification → Webhook, JSON) or another certificate monitor. Each endpoint has its own token.'))
                ->schema([
                    TextEntry::make('webhook_url')
                        ->label(__('Webhook URL (POST JSON)'))
                        ->state(fn (Endpoint $record) => $record->webhookUrl())
                        ->copyable()
                        ->fontFamily(FontFamily::Mono)
                        ->columnSpanFull(),
                    TextEntry::make('last_checked_at')
                        ->label(__('Last webhook'))
                        ->dateTime('M j, Y H:i')
                        ->placeholder(__('None yet')),
                    TextEntry::make('last_payload')
                        ->label(__('Last payload'))
                        ->formatStateUsing(fn ($state) => $state
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            : '—')
                        ->columnSpanFull(),
                ]),
            Section::make('Notifications')
                ->columns(Breakpoints::THREE)
                ->schema([
                    IconEntry::make('notify_30')->label(__('30 days'))->boolean(),
                    IconEntry::make('notify_14')->label(__('14 days'))->boolean(),
                    IconEntry::make('notify_7')->label(__('7 days'))->boolean(),
                    IconEntry::make('notify_1')->label(__('1 day'))->boolean(),
                    IconEntry::make('notify_expired')->label(__('Expired'))->boolean(),
                    IconEntry::make('notify_customer')->label(__('Email customer'))->boolean(),
                    TextEntry::make('notes')->label(__('Notes'))->placeholder(__('—'))->columnSpanFull(),
                ]),
        ]);
    }
}

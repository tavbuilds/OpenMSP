<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 85;

    public static function getNavigationLabel(): string
    {
        return __(static::$navigationLabel ?? parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return __(static::$modelLabel ?? parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$pluralModelLabel ?? parent::getPluralModelLabel());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')->label(__('Time'))->dateTime('M j, Y H:i:s'),
                    TextEntry::make('event')->label(__('Action'))->badge(),
                    TextEntry::make('user.name')->label(__('User'))->placeholder(__('System / unknown')),
                    TextEntry::make('ip_address')->label(__('IP'))->placeholder(__('—')),
                    TextEntry::make('subject')
                        ->label(__('Subject'))
                        ->state(fn (AuditLog $record) => $record->subjectLabel())
                        ->columnSpanFull(),
                ]),
            Section::make('Old')
                ->schema([
                    TextEntry::make('old_values')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—')
                        ->columnSpanFull(),
                ]),
            Section::make('New')
                ->schema([
                    TextEntry::make('new_values')
                        ->hiddenLabel()
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (AuditLog $record) => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('created_at')->label(__('Time'))->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('user.name')->label(__('User'))->placeholder(__('—'))->searchable()->visibleFrom('md'),
                TextColumn::make('event')->label(__('Action'))->badge(),
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->state(fn (AuditLog $record) => $record->subjectLabel())
                    ->searchable(['auditable_type']),
                TextColumn::make('ip_address')->label(__('IP'))->toggleable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}

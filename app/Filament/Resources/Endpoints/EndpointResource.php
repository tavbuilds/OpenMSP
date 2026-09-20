<?php

namespace App\Filament\Resources\Endpoints;

use App\Filament\Resources\Endpoints\Pages\CreateEndpoint;
use App\Filament\Resources\Endpoints\Pages\EditEndpoint;
use App\Filament\Resources\Endpoints\Pages\ListEndpoints;
use App\Filament\Resources\Endpoints\Pages\ViewEndpoint;
use App\Filament\Resources\Endpoints\Schemas\EndpointForm;
use App\Filament\Resources\Endpoints\Schemas\EndpointInfolist;
use App\Filament\Resources\Endpoints\Tables\EndpointsTable;
use App\Models\Endpoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EndpointResource extends Resource
{
    protected static ?string $model = Endpoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Endpoints';

    protected static ?string $modelLabel = 'endpoint';

    protected static ?string $pluralModelLabel = 'endpoints';

    protected static ?int $navigationSort = 3;

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->name;
    }

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

    public static function form(Schema $schema): Schema
    {
        return EndpointForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EndpointInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EndpointsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEndpoints::route('/'),
            'create' => CreateEndpoint::route('/create'),
            'view' => ViewEndpoint::route('/{record}'),
            'edit' => EditEndpoint::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageContracts() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageContracts() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->canAdminister() ?? false;
    }
}

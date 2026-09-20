<?php

namespace App\Filament\Resources\PurchaseBundles;

use App\Filament\Resources\PurchaseBundles\Pages\CreatePurchaseBundle;
use App\Filament\Resources\PurchaseBundles\Pages\EditPurchaseBundle;
use App\Filament\Resources\PurchaseBundles\Pages\ListPurchaseBundles;
use App\Filament\Resources\PurchaseBundles\Pages\ViewPurchaseBundle;
use App\Filament\Resources\PurchaseBundles\RelationManagers\ContractsRelationManager;
use App\Filament\Resources\PurchaseBundles\Schemas\PurchaseBundleForm;
use App\Filament\Resources\PurchaseBundles\Schemas\PurchaseBundleInfolist;
use App\Filament\Resources\PurchaseBundles\Tables\PurchaseBundlesTable;
use App\Filament\Concerns\AuthorizesByRole;
use App\Models\PurchaseBundle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PurchaseBundleResource extends Resource
{
    use AuthorizesByRole;

    protected static ?string $model = PurchaseBundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Purchase bundles';

    protected static ?string $modelLabel = 'purchase bundle';

    protected static ?string $pluralModelLabel = 'purchase bundles';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 12;

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
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
        return PurchaseBundleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseBundleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseBundlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ContractsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseBundles::route('/'),
            'create' => CreatePurchaseBundle::route('/create'),
            'view' => ViewPurchaseBundle::route('/{record}'),
            'edit' => EditPurchaseBundle::route('/{record}/edit'),
        ];
    }
}

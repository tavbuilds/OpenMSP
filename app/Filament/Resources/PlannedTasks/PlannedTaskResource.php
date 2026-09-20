<?php

namespace App\Filament\Resources\PlannedTasks;

use App\Filament\Concerns\AuthorizesByRole;
use App\Filament\Resources\PlannedTasks\Pages\CreatePlannedTask;
use App\Filament\Resources\PlannedTasks\Pages\EditPlannedTask;
use App\Filament\Resources\PlannedTasks\Pages\ListPlannedTasks;
use App\Filament\Resources\PlannedTasks\Pages\ViewPlannedTask;
use App\Filament\Resources\PlannedTasks\Schemas\PlannedTaskForm;
use App\Filament\Resources\PlannedTasks\Schemas\PlannedTaskInfolist;
use App\Filament\Resources\PlannedTasks\Tables\PlannedTasksTable;
use App\Models\PlannedTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlannedTaskResource extends Resource
{
    use AuthorizesByRole;

    protected static ?string $model = PlannedTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Planning';

    protected static ?string $modelLabel = 'planned task';

    protected static ?string $pluralModelLabel = 'planned tasks';

    protected static ?int $navigationSort = 4;

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->title;
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
        return PlannedTaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlannedTaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlannedTasksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlannedTasks::route('/'),
            'create' => CreatePlannedTask::route('/create'),
            'view' => ViewPlannedTask::route('/{record}'),
            'edit' => EditPlannedTask::route('/{record}/edit'),
        ];
    }
}

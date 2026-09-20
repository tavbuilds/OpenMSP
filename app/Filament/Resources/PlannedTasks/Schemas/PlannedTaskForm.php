<?php

namespace App\Filament\Resources\PlannedTasks\Schemas;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskPriority;
use App\Enums\PlannedTaskStatus;
use App\Support\Breakpoints;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlannedTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Task')
                ->columns(Breakpoints::TWO)
                ->schema([
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(180)
                        ->helperText(__('e.g. Office move or Microsoft 365 tenant migration')),
                    Select::make('company_id')
                        ->label(__('Customer'))
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn () => request()->integer('company_id') ?: null)
                        ->placeholder(__('Internal (no customer)')),
                    Select::make('kind')
                        ->label(__('Type'))
                        ->options(PlannedTaskKind::class)
                        ->default(PlannedTaskKind::Other->value)
                        ->required(),
                    Select::make('status')
                        ->label(__('Status'))
                        ->options(PlannedTaskStatus::class)
                        ->default(PlannedTaskStatus::Planned->value)
                        ->required(),
                    Select::make('priority')
                        ->label(__('Priority'))
                        ->options(PlannedTaskPriority::class)
                        ->default(PlannedTaskPriority::Normal->value)
                        ->required(),
                    DatePicker::make('due_on')
                        ->label(__('Deadline'))
                        ->required()
                        ->native(false),
                    Select::make('assigned_user_id')
                        ->label(__('Assignee'))
                        ->relationship('assignedUser', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder(__('Unassigned')),
                    TextInput::make('location_from')
                        ->label(__('From'))
                        ->maxLength(180)
                        ->placeholder(__('Current site')),
                    TextInput::make('location_to')
                        ->label(__('To'))
                        ->maxLength(180)
                        ->placeholder(__('New site')),
                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->columnSpanFull(),
                ]),
            Section::make('Notifications')
                ->description(__('Staff (admin/manager) and the assignee receive the enabled steps. Each toggle is per task.'))
                ->columns(Breakpoints::TWO)
                ->schema([
                    Toggle::make('notify_30')->label(__('30 days before'))->default(true),
                    Toggle::make('notify_14')->label(__('14 days before'))->default(true),
                    Toggle::make('notify_7')->label(__('7 days before'))->default(true),
                    Toggle::make('notify_1')->label(__('1 day before'))->default(true),
                    Toggle::make('notify_expired')->label(__('When it has expired'))->default(true),
                ]),
        ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserAdministration;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('bold'),
                TextColumn::make('email')->label(__('Email'))->searchable()->copyable()->visibleFrom('md'),
                TextColumn::make('role')->label(__('Role'))->badge()->sortable(),
                TextColumn::make('tokens_count')
                    ->label(__('API tokens'))
                    ->counts('tokens')
                    ->badge()
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('created_at')->label(__('Created'))->dateTime('M j, Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('Role'))->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => UserAdministration::isLastAdmin($record) || auth()->id() === $record->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $actor = auth()->user();
                            $deleted = 0;
                            foreach ($records as $record) {
                                if ($actor instanceof User && UserAdministration::canDelete($actor, $record)) {
                                    $record->delete();
                                    $deleted++;
                                }
                            }
                            Notification::make()
                                ->title("{$deleted} user(s) deleted")
                                ->body('The last administrator and your own account are always kept.')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}

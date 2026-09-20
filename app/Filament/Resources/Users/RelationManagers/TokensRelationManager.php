<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\User;
use App\Support\UserAdministration;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'API tokens';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('last_used_at')->label(__('Last used'))->dateTime('M j, Y H:i')->placeholder(__('Never')),
                TextColumn::make('created_at')->label(__('Created'))->dateTime('M j, Y H:i'),
            ])
            ->headerActions([
                Action::make('createToken')
                    ->label(__('Create token'))
                    ->form([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->default('agent'),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        if (! $owner instanceof User) {
                            return;
                        }
                        $plain = UserAdministration::createToken($owner, $data['name']);
                        Notification::make()
                            ->title(__('Token created'))
                            ->body("Copy now: {$plain}\nThis value is shown only once.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->label(__('Revoke')),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Support\Breakpoints;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(Breakpoints::TWO)
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('role')
                    ->label(__('Role'))
                    ->options(UserRole::class)
                    ->required()
                    ->default(UserRole::Viewer->value)
                    ->helperText(__('Administrators and managers can manage users, tokens, and settings.')),
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave empty to keep the current password.'
                        : null),
            ]);
    }
}

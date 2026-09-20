<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Name'))->required(),
            TextInput::make('email')->label(__('Email'))->email()
                ->helperText(__('With an email address this person can sign in to the customer portal via magic link.')),
            TextInput::make('phone')->label(__('Phone'))->tel(),
            TextInput::make('job_title')->label(__('Job title')),
            Toggle::make('is_primary')->label(__('Primary contact'))->default(false),
        ]);
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->canManageContracts() ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('bold')
                    ->description(fn ($record) => $record->job_title),
                TextColumn::make('email')->label(__('Email'))->placeholder(__('—'))->copyable()->visibleFrom('md'),
                TextColumn::make('phone')->label(__('Phone'))->placeholder(__('—'))->visibleFrom('lg'),
                IconColumn::make('is_primary')->label(__('Primary'))->boolean()->visibleFrom('md'),
                IconColumn::make('can_use_portal')
                    ->label(__('Portal'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->canUsePortal())
                    ->tooltip(fn ($record) => $record->canUsePortal()
                        ? 'Can sign in to /portal via magic link'
                        : 'No email — no portal access'),
                TextColumn::make('portal_last_login_at')
                    ->label(__('Last portal sign-in'))
                    ->dateTime('M j, Y H:i')
                    ->placeholder(__('Never'))
                    ->toggleable()
                    ->visibleFrom('xl'),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add contact')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

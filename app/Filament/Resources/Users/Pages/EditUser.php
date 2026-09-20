<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\UserAdministration;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label(__('Reset password'))
                ->icon(Heroicon::OutlinedKey)
                ->color('warning')
                ->form([
                    TextInput::make('password')
                        ->label(__('New password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->confirmed(),
                    TextInput::make('password_confirmation')
                        ->label(__('Confirm password'))
                        ->password()
                        ->required(),
                    Toggle::make('revoke_tokens')
                        ->label(__('Revoke all API tokens for this user'))
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    UserAdministration::resetPassword(
                        $record,
                        $data['password'],
                        (bool) ($data['revoke_tokens'] ?? true),
                    );
                    Notification::make()
                        ->title(__('Password reset'))
                        ->success()
                        ->send();
                }),
            Action::make('revokeTokens')
                ->label(__('Revoke tokens'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Revoke all tokens for this user?'))
                ->action(function (): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    $n = UserAdministration::revokeTokens($record);
                    Notification::make()
                        ->title("{$n} token(s) revoked")
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $role = $data['role'] ?? null;
        $enum = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        if ($record instanceof User && $enum && ! UserAdministration::canChangeRole($record, $enum)) {
            $data['role'] = UserRole::Admin->value;
            Notification::make()
                ->title(__('The last administrator cannot be demoted'))
                ->warning()
                ->send();
        }

        return $data;
    }
}

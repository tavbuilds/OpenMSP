<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\UserAdministration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API tokens';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'api-tokens';

    protected static ?string $title = 'API tokens';

    protected string $view = 'filament.pages.api-tokens';

    public ?string $plainTextToken = null;

    public static function getNavigationLabel(): string
    {
        return __('API tokens');
    }

    public function getTitle(): string|Htmlable
    {
        return __('API tokens');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return is_string(static::$navigationGroup)
            ? __(static::$navigationGroup)
            : parent::getNavigationGroup();
    }

    public function mcpUrl(): string
    {
        return McpConnection::endpointUrl();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PersonalAccessToken::query()
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', auth()->id())
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('last_used_at')->label(__('Last used'))->dateTime('M j, Y H:i')->placeholder(__('Never')),
                TextColumn::make('created_at')->label(__('Created'))->dateTime('M j, Y H:i'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('Revoke'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PersonalAccessToken $record): void {
                        $record->delete();
                        Notification::make()->title(__('Token revoked'))->success()->send();
                    }),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('Create token'))
                ->icon(Heroicon::OutlinedPlus)
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->default('mcp')
                        ->helperText(__('e.g. mcp, grok, ci, or the name of the integration.')),
                ])
                ->action(function (array $data): void {
                    $this->plainTextToken = UserAdministration::createToken(auth()->user(), $data['name']);
                    Notification::make()
                        ->title(__('Token created'))
                        ->body(__('Copy the token below. It is shown only once. The MCP endpoint is listed next to it.'))
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('revokeAll')
                ->label(__('Revoke all my tokens'))
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $n = UserAdministration::revokeTokens(auth()->user());
                    $this->plainTextToken = null;
                    Notification::make()->title("{$n} token(s) revoked")->success()->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Mcp\ToolCatalog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class McpConnection extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'MCP';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 82;

    protected static ?string $slug = 'mcp';

    protected static ?string $title = 'MCP';

    protected string $view = 'filament.pages.mcp-connection';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return is_string(static::$navigationGroup)
            ? __(static::$navigationGroup)
            : parent::getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('MCP');
    }

    public function getTitle(): string|Htmlable
    {
        return __('MCP');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function endpointUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/mcp';
    }

    public function getUrlForAgents(): string
    {
        return self::endpointUrl();
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    public function tools(): array
    {
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
        ], ToolCatalog::tools());
    }
}

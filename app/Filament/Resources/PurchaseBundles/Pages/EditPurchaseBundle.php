<?php

namespace App\Filament\Resources\PurchaseBundles\Pages;

use App\Filament\Resources\PurchaseBundles\PurchaseBundleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseBundle extends EditRecord
{
    protected static string $resource = PurchaseBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

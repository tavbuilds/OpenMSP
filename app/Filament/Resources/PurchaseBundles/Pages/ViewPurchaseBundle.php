<?php

namespace App\Filament\Resources\PurchaseBundles\Pages;

use App\Filament\Resources\PurchaseBundles\PurchaseBundleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseBundle extends ViewRecord
{
    protected static string $resource = PurchaseBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

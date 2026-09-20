<?php

namespace App\Filament\Resources\PurchaseBundles\Pages;

use App\Filament\Resources\PurchaseBundles\PurchaseBundleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseBundles extends ListRecords
{
    protected static string $resource = PurchaseBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'vendor' => VendorResource::make($this->whenLoaded('vendor')),
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type?->value ?? $this->type,
            'default_cost_price' => $this->default_cost_price,
            'default_sale_price' => $this->default_sale_price,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle?->value ?? $this->billing_cycle,
            'description' => $this->description,
            'active' => $this->active,
            'contracts_count' => $this->when(isset($this->contracts_count), $this->contracts_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

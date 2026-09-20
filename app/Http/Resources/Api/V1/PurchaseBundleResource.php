<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseBundle */
class PurchaseBundleResource extends JsonResource
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
            'reference' => $this->reference,
            'total_cost' => $this->total_cost,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle?->value ?? $this->billing_cycle,
            'allocation_method' => $this->allocation_method,
            'start_date' => $this->start_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'notes' => $this->notes,
            'annual_cost' => $this->annual_cost,
            'active_contracts_count' => $this->activeContractsCount(),
            'allocated_annual_cost_per_contract' => $this->allocatedAnnualCostPerContract(),
            'annual_resale' => $this->annualResale(),
            'recovery_percentage' => $this->recoveryPercentage(),
            'contracts_count' => $this->when(isset($this->contracts_count), $this->contracts_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

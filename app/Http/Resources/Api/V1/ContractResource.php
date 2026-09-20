<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Contract */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeLicenseKeys = $request->user()?->canManageContracts() ?? false;
        $isShow = $request->routeIs('*.show') || $request->route()?->getActionMethod() === 'show';

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'purchase_bundle_id' => $this->purchase_bundle_id,
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'vendor' => VendorResource::make($this->whenLoaded('vendor')),
            'name' => $this->name,
            'reference' => $this->reference,
            'type' => $this->type?->value ?? $this->type,
            'quantity' => $this->quantity,
            'cost_price' => $this->cost_price,
            'sale_price' => $this->sale_price,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle?->value ?? $this->billing_cycle,
            'start_date' => $this->start_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'notice_period_days' => $this->notice_period_days,
            'auto_renew' => $this->auto_renew,
            'status' => $this->status?->value ?? $this->status,
            'next_invoice_date' => $this->next_invoice_date?->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toDateString(),
            // Encrypted at rest; only managers (sales+) see plaintext on show; omitted from index lists.
            'license_keys' => $this->when(
                $canSeeLicenseKeys && ($isShow || $request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')),
                $this->license_keys
            ),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

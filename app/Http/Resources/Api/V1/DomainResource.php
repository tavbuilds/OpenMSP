<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Domain */
class DomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'extension' => $this->extension,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'product_id' => $this->product_id,
            'expires_at' => $this->expires_at?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'actionable_date' => $this->expiryDate()?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'expiry_status' => $this->expiryStatus(),
            'auto_renew' => (bool) $this->auto_renew,
            'auto_renew_source' => $this->auto_renew_source,
            'status' => $this->status,
            'registrar_status_label' => $this->registrarStatusLabel(),
            'source' => $this->source?->value ?? $this->source,
            'removed_at' => $this->removed_at?->toIso8601String(),
            'is_removed' => $this->isRemoved(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'notes' => $this->notes,
            'notes_visible_to_customer' => (bool) $this->notes_visible_to_customer,
            'cost_price' => $this->whenLoaded('product', fn () => $this->costPrice()),
            'currency' => $this->whenLoaded('product', fn () => $this->product?->currency),
            'notify_30' => (bool) $this->notify_30,
            'notify_14' => (bool) $this->notify_14,
            'notify_7' => (bool) $this->notify_7,
            'notify_1' => (bool) $this->notify_1,
            'notify_expired' => (bool) $this->notify_expired,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

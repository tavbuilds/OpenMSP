<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Endpoint */
class EndpointResource extends JsonResource
{
    /**
     * The webhook URL embeds the token. Listings omit it; show and writes include it.
     */
    public bool $withWebhook = false;

    public function showingWebhook(): static
    {
        $this->withWebhook = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'kind' => $this->kind?->value ?? $this->kind,
            'hostname' => $this->hostname,
            'url' => $this->url,
            'expires_at' => $this->expires_at?->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'status' => $this->last_status ?: $this->computeStatus(),
            'source' => $this->source?->value ?? $this->source,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'notes' => $this->notes,
            'notify_30' => (bool) $this->notify_30,
            'notify_14' => (bool) $this->notify_14,
            'notify_7' => (bool) $this->notify_7,
            'notify_1' => (bool) $this->notify_1,
            'notify_expired' => (bool) $this->notify_expired,
            'notify_customer' => (bool) $this->notify_customer,
            'webhook_url' => $this->when($this->withWebhook, fn () => $this->webhookUrl()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

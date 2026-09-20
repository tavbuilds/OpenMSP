<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlannedTask */
class PlannedTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'assigned_user_id' => $this->assigned_user_id,
            'assignee_name' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->name),
            'kind' => $this->kind?->value ?? $this->kind,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'due_on' => $this->due_on?->toDateString(),
            'days_until_due' => $this->daysUntilDue(),
            'overdue' => $this->isOverdue(),
            'location_from' => $this->location_from,
            'location_to' => $this->location_to,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

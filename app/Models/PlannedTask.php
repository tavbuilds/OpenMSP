<?php

namespace App\Models;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskPriority;
use App\Enums\PlannedTaskStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedTask extends Model
{
    use Auditable;

    protected $fillable = [
        'company_id', 'assigned_user_id', 'title', 'kind', 'status', 'priority',
        'due_on', 'location_from', 'location_to', 'notes', 'is_demo',
    ];

    protected $attributes = [
        'kind' => 'other',
        'status' => 'planned',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PlannedTaskKind::class,
            'status' => PlannedTaskStatus::class,
            'priority' => PlannedTaskPriority::class,
            'due_on' => 'date',
            'is_demo' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            PlannedTaskStatus::Done->value,
            PlannedTaskStatus::Cancelled->value,
        ]);
    }

    /** Signed days until deadline (negative = overdue). */
    public function daysUntilDue(): int
    {
        return (int) round(
            now()->startOfDay()->diffInDays($this->due_on->copy()->startOfDay(), false)
        );
    }

    public function isOverdue(): bool
    {
        return $this->status?->isOpen() && $this->daysUntilDue() < 0;
    }

    public function dueColor(): string
    {
        if (! $this->status?->isOpen()) {
            return 'gray';
        }

        $days = $this->daysUntilDue();
        if ($days < 0) {
            return 'danger';
        }
        if ($days <= 7) {
            return 'warning';
        }

        return 'success';
    }
}

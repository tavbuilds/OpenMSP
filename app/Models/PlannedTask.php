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

    /** @var array<int, string> */
    public const OFFSET_FLAGS = [
        30 => 'notify_30',
        14 => 'notify_14',
        7 => 'notify_7',
        1 => 'notify_1',
    ];

    protected $fillable = [
        'company_id', 'assigned_user_id', 'title', 'kind', 'status', 'priority',
        'due_on', 'location_from', 'location_to', 'notes',
        'notify_30', 'notify_14', 'notify_7', 'notify_1', 'notify_expired',
        'sent_offsets', 'expired_notified_at', 'is_demo',
    ];

    protected $attributes = [
        'kind' => 'other',
        'status' => 'planned',
        'priority' => 'normal',
        'notify_30' => true,
        'notify_14' => true,
        'notify_7' => true,
        'notify_1' => true,
        'notify_expired' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => PlannedTaskKind::class,
            'status' => PlannedTaskStatus::class,
            'priority' => PlannedTaskPriority::class,
            'due_on' => 'date',
            'notify_30' => 'boolean',
            'notify_14' => 'boolean',
            'notify_7' => 'boolean',
            'notify_1' => 'boolean',
            'notify_expired' => 'boolean',
            'sent_offsets' => 'array',
            'expired_notified_at' => 'datetime',
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

    public function dueNotification(): int|string|null
    {
        if (! $this->status?->isOpen()) {
            return null;
        }

        $days = $this->daysUntilDue();
        $sent = array_map('intval', $this->sent_offsets ?? []);

        if ($days <= 0) {
            return $this->notify_expired && $this->expired_notified_at === null
                ? 'expired'
                : null;
        }

        $tightest = null;
        foreach (self::OFFSET_FLAGS as $offset => $flag) {
            if ($days <= $offset && $this->{$flag} && ! in_array($offset, $sent, true)) {
                $tightest = $offset;
            }
        }

        return $tightest;
    }

    public function markNotified(int|string $which): void
    {
        if ($which === 'expired') {
            $this->forceFill(['expired_notified_at' => now()])->save();

            return;
        }

        $sent = array_map('intval', $this->sent_offsets ?? []);
        $which = (int) $which;
        foreach (array_keys(self::OFFSET_FLAGS) as $offset) {
            if ($offset >= $which) {
                $sent[] = $offset;
            }
        }
        $this->forceFill([
            'sent_offsets' => array_values(array_unique($sent)),
        ])->save();
    }
}

<?php

namespace App\Models\Concerns;

/**
 * Reminders at 30/14/7/1 days and once on the day itself.
 *
 * Needs an `expires_at` date attribute, the four notify_* booleans, a
 * `notify_expired` boolean, a `sent_offsets` array cast and an
 * `expired_notified_at` timestamp. The offsets already sent are remembered so
 * a daily run mails once per step, not once per day.
 */
trait WarnsBeforeExpiry
{
    /** @var array<int, string> */
    public const OFFSET_FLAGS = [
        30 => 'notify_30',
        14 => 'notify_14',
        7 => 'notify_7',
        1 => 'notify_1',
    ];

    /** Signed days until expiry (negative = already expired). Null if unknown. */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return (int) round(
            now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false)
        );
    }

    /**
     * Tightest due notification for today: expired, or 1/7/14/30 if that toggle is on
     * and that offset was not yet sent this expiry-cycle.
     *
     * @return 'expired'|int|null
     */
    public function dueNotification(): int|string|null
    {
        $days = $this->daysUntilExpiry();
        if ($days === null) {
            return null;
        }

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
            $this->forceFill([
                'expired_notified_at' => now(),
                ...$this->expiryNotificationExtras(),
            ])->save();

            return;
        }

        $sent = array_map('intval', $this->sent_offsets ?? []);
        $which = (int) $which;
        // Sending the 7-day mail also retires 14 and 30: they are past.
        foreach (array_keys(self::OFFSET_FLAGS) as $offset) {
            if ($offset >= $which) {
                $sent[] = $offset;
            }
        }
        $this->forceFill([
            'sent_offsets' => array_values(array_unique($sent)),
            ...$this->expiryNotificationExtras(),
        ])->save();
    }

    /**
     * A renewed record starts a new cycle, otherwise it would stay silent
     * forever on the strength of last year's mail.
     *
     * @return array<string, mixed>
     */
    public function freshExpiryCycle(): array
    {
        return [
            'sent_offsets' => [],
            'expired_notified_at' => null,
        ];
    }

    /**
     * Columns a model wants written alongside every reminder it sends.
     *
     * @return array<string, mixed>
     */
    protected function expiryNotificationExtras(): array
    {
        return [];
    }
}

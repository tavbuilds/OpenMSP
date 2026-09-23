<?php

namespace App\Models;

use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\WarnsBeforeExpiry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    use Auditable;
    use WarnsBeforeExpiry;

    protected $fillable = [
        'company_id', 'name', 'kind', 'hostname', 'url', 'expires_at',
        'source', 'webhook_token', 'last_status', 'last_checked_at', 'last_payload',
        'notes',
        'notify_30', 'notify_14', 'notify_7', 'notify_1', 'notify_expired', 'notify_customer',
        'sent_offsets', 'expired_notified_at', 'is_demo',
    ];

    protected $hidden = [
        'webhook_token',
    ];

    protected $attributes = [
        'notify_30' => true,
        'notify_14' => true,
        'notify_7' => true,
        'notify_1' => true,
        'notify_expired' => true,
        'notify_customer' => false,
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'kind' => EndpointKind::class,
            'source' => EndpointSource::class,
            'expires_at' => 'date',
            'last_checked_at' => 'datetime',
            'last_payload' => 'array',
            'notify_30' => 'boolean',
            'notify_14' => 'boolean',
            'notify_7' => 'boolean',
            'notify_1' => 'boolean',
            'notify_expired' => 'boolean',
            'notify_customer' => 'boolean',
            'sent_offsets' => 'array',
            'expired_notified_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    /** @return list<string> */
    protected function auditRedactedFields(): array
    {
        return ['license_keys', 'password', 'remember_token', 'webhook_token', 'last_payload'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $endpoint): void {
            if (! filled($endpoint->webhook_token)) {
                $endpoint->webhook_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function rotateWebhookToken(): string
    {
        $this->forceFill(['webhook_token' => Str::random(40)])->save();

        return $this->webhook_token;
    }

    public function webhookUrl(): string
    {
        return url('/hooks/endpoints/'.$this->webhook_token);
    }

    public function computeStatus(): string
    {
        $days = $this->daysUntilExpiry();
        if ($days === null) {
            return 'unknown';
        }
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'warning';
        }

        return 'ok';
    }

    public function statusLabel(): string
    {
        return match ($this->last_status ?: $this->computeStatus()) {
            'ok' => 'OK',
            'warning' => 'Expiring soon',
            'expired' => 'Expired',
            default => 'Unknown',
        };
    }

    public function statusColor(): string
    {
        return match ($this->last_status ?: $this->computeStatus()) {
            'ok' => 'success',
            'warning' => 'warning',
            'expired' => 'danger',
            default => 'gray',
        };
    }

    /** Every reminder also refreshes the status shown in the table. */
    protected function expiryNotificationExtras(): array
    {
        return ['last_status' => $this->computeStatus()];
    }

    /**
     * @param  array{expires_at: mixed, hostname: ?string, message: ?string}  $parsed
     * @param  array<string, mixed>  $payload
     */
    public function applyWebhook(array $parsed, array $payload): void
    {
        $newExpiry = $parsed['expires_at'] ?? null;
        $resetCycle = false;
        if ($newExpiry && $this->expires_at?->toDateString() !== $newExpiry->toDateString()) {
            $resetCycle = true;
        }

        $fill = [
            'source' => EndpointSource::Webhook,
            'last_checked_at' => now(),
            'last_payload' => $payload,
        ];
        if ($newExpiry) {
            $fill['expires_at'] = $newExpiry->toDateString();
        }
        if (filled($parsed['hostname'] ?? null) && ! filled($this->hostname)) {
            $fill['hostname'] = $parsed['hostname'];
        }
        if ($resetCycle) {
            $fill = [...$fill, ...$this->freshExpiryCycle()];
        }

        $this->forceFill($fill);
        $this->last_status = $this->computeStatus();
        $this->save();
    }
}

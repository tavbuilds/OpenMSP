<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Lightweight auditing: records create/update/delete events with changed
 * attributes into the audit_logs table. Sensitive fields are redacted.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->getAttributes()));

        static::updated(function (Model $model) {
            $model->writeAudit('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->getOriginal(), []));
    }

    /** @return list<string> */
    protected function auditRedactedFields(): array
    {
        return ['license_keys', 'password', 'remember_token', 'webhook_token'];
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $this->redact($old),
            'new_values' => $this->redact($new),
            'ip_address' => request()?->ip(),
        ]);
    }

    protected function redact(array $values): array
    {
        foreach ($this->auditRedactedFields() as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '••• redacted •••';
            }
        }

        return $values;
    }
}

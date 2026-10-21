<?php

namespace App\Models;

use App\Enums\DomainSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\WarnsBeforeExpiry;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A registered domain name.
 *
 * Not a contract: a domain has no quantity, no sale price of its own and no
 * portal collection. What it has is an expiry date, an auto-renew flag and a
 * customer — and that customer is assigned here, never taken from the
 * registrar, whose owner handles do not map onto this platform's companies.
 */
class Domain extends Model
{
    use Auditable;
    use WarnsBeforeExpiry;

    protected $fillable = [
        'company_id', 'product_id', 'name', 'extension',
        'expires_at', 'renewal_date', 'auto_renew', 'auto_renew_source', 'status',
        'notes', 'notes_visible_to_customer',
        'source', 'source_id', 'last_synced_at', 'removed_at',
        'notify_30', 'notify_14', 'notify_7', 'notify_1', 'notify_expired',
        'sent_offsets', 'expired_notified_at', 'is_demo',
    ];

    protected $attributes = [
        'auto_renew' => false,
        'notes_visible_to_customer' => false,
        'notify_30' => true,
        'notify_14' => true,
        'notify_7' => true,
        'notify_1' => true,
        'notify_expired' => true,
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'source' => DomainSource::class,
            'expires_at' => 'date',
            'renewal_date' => 'date',
            'last_synced_at' => 'datetime',
            'removed_at' => 'datetime',
            'auto_renew' => 'boolean',
            'notes_visible_to_customer' => 'boolean',
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

    protected static function booted(): void
    {
        // One spelling of a domain name, so a manual entry and a synced one
        // are the same row rather than two.
        static::saving(function (self $domain): void {
            $domain->name = Str::lower(trim((string) $domain->name));
            if (! filled($domain->extension) && str_contains($domain->name, '.')) {
                $domain->extension = Str::after($domain->name, '.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** The catalog entry for this extension, which carries the purchase price. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The date to act on.
     *
     * Openprovider publishes two, and says of `expiration_date` that it "is
     * not the primary reference for domain expiration" while `renewal_date`
     * is "the date by which a domain must be renewed to avoid expiration".
     * So the countdown and the reminders run on the renewal date, and fall
     * back to the expiry date for a domain typed in by hand.
     */
    public function expiryDate(): ?CarbonInterface
    {
        return $this->renewal_date ?? $this->expires_at;
    }

    /** SQL for the same choice, for scopes and sorting. */
    public const ACTIONABLE_DATE = 'coalesce(renewal_date, expires_at)';

    /** Domains whose renewal is close enough to act on. */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereRaw(self::ACTIONABLE_DATE.' is not null')
            ->whereRaw(self::ACTIONABLE_DATE.' <= ?', [now()->addDays($days)->toDateString()]);
    }

    /**
     * Soonest renewal first, with the dateless ones last.
     *
     * Postgres puts nulls last on an ascending sort and sqlite puts them
     * first, so the order is spelled out rather than inherited.
     */
    public function scopeOrderByDue(Builder $query, string $direction = 'asc'): Builder
    {
        return $query
            ->orderByRaw(self::ACTIONABLE_DATE.' is null')
            ->orderByRaw(self::ACTIONABLE_DATE.' '.($direction === 'desc' ? 'desc' : 'asc'));
    }

    /** Still on the registrar's books, so still ours to watch. */
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }

    public function scopeRemoved(Builder $query): Builder
    {
        return $query->whereNotNull('removed_at');
    }

    /** The registrar stopped returning this domain; it is not ours any more. */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function expiryStatus(): string
    {
        // A domain the registrar let go is not an emergency, it is history.
        // Leaving it red would keep it at the top of a list of things to do.
        if ($this->isRemoved()) {
            return 'removed';
        }

        $days = $this->daysUntilExpiry();

        return match (true) {
            $days === null => 'unknown',
            $days < 0 => 'expired',
            $days <= 30 => 'warning',
            default => 'ok',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->expiryStatus()) {
            'ok' => __('OK'),
            'warning' => __('Expiring soon'),
            'expired' => __('Expired'),
            'removed' => __('No longer at the registrar'),
            default => __('Unknown'),
        };
    }

    public function statusColor(): string
    {
        return match ($this->expiryStatus()) {
            'ok' => 'success',
            'warning' => 'warning',
            'expired' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Openprovider's own status code (ACT, PEN, RGP, …). Kept verbatim so an
     * unknown code still shows rather than becoming "other".
     */
    public function registrarStatusLabel(): ?string
    {
        if (! filled($this->status)) {
            return null;
        }

        return match (Str::upper($this->status)) {
            'ACT' => __('Active'),
            'PEN', 'REQ', 'PRE' => __('Pending'),
            'RGP', 'SCH' => __('Quarantine'),
            'DEL' => __('Deleted'),
            'FAI' => __('Failed'),
            'REJ' => __('Rejected'),
            default => $this->status,
        };
    }

    /**
     * What that status means at a glance.
     *
     * Every domain used to wear the same blue badge, which told an operator
     * scanning the list nothing: a live domain and one in quarantine looked
     * identical. Green is fine, amber is in progress, red needs doing
     * something about.
     *
     * A code we do not recognise stays grey rather than being guessed at —
     * a wrong colour on a status is worse than no colour.
     */
    public function registrarStatusColor(): string
    {
        if ($this->isRemoved()) {
            return 'gray';
        }

        return match (Str::upper((string) $this->status)) {
            'ACT' => 'success',
            'PEN', 'REQ', 'PRE', 'SCH' => 'warning',
            'RGP', 'DEL', 'FAI', 'REJ' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Openprovider answers "on", "off" or "default" per domain. "default"
     * points at an account setting their API does not expose, so the operator
     * tells us what it means under Catalog → Openprovider.
     */
    public static function resolveAutoRenew(?string $source): bool
    {
        return match (Str::lower(trim((string) $source))) {
            'on' => true,
            'off' => false,
            // "default", and anything we do not recognise, follows the account.
            default => PlatformSettings::openProviderDefaultAutoRenew(),
        };
    }

    /** Re-reads every synced domain against the current account default. */
    public static function reapplyAutoRenewDefault(): int
    {
        $changed = 0;

        foreach (static::query()->whereNotNull('auto_renew_source')->get() as $domain) {
            $effective = static::resolveAutoRenew($domain->auto_renew_source);
            if ($domain->auto_renew !== $effective) {
                $domain->forceFill(['auto_renew' => $effective])->save();
                $changed++;
            }
        }

        return $changed;
    }

    /** "On", "Off", or "Account default (on)" — what the registrar told us. */
    public function autoRenewLabel(): string
    {
        return match (Str::lower(trim((string) $this->auto_renew_source))) {
            'on' => __('On'),
            'off' => __('Off'),
            'default' => $this->auto_renew
                ? __('Account default (on)')
                : __('Account default (off)'),
            default => $this->auto_renew ? __('On') : __('Off'),
        };
    }

    /** The note, only when it was deliberately shared with the customer. */
    public function customerVisibleNotes(): ?string
    {
        return $this->notes_visible_to_customer && filled($this->notes)
            ? $this->notes
            : null;
    }

    /** Purchase price per year for this extension, from the catalog. */
    public function costPrice(): ?float
    {
        $cost = $this->product?->effective_cost_price;

        return $cost !== null ? (float) $cost : null;
    }
}

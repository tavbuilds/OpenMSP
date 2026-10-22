<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use Auditable;

    protected $fillable = [
        'company_id', 'parent_id', 'is_group',
        'product_id', 'vendor_id', 'purchase_bundle_id',
        'name', 'reference', 'type',
        'quantity', 'cost_price', 'sale_price', 'currency', 'billing_cycle',
        'start_date', 'renewal_date', 'notice_period_days', 'auto_renew',
        'status', 'next_invoice_date', 'cancelled_at',
        'license_keys', 'notes', 'notify_renewals', 'is_demo',
        'auto_collect', 'stripe_subscription_id', 'stripe_subscription_item_id',
        'stripe_payment_method_id', 'stripe_mandate_id', 'stripe_payment_status',
        'auto_collect_enabled_at',
        'source', 'source_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'start_date' => 'date',
        'renewal_date' => 'date',
        'next_invoice_date' => 'date',
        'cancelled_at' => 'date',
        'auto_renew' => 'boolean',
        'auto_collect' => 'boolean',
        'auto_collect_enabled_at' => 'datetime',
        'notice_period_days' => 'integer',
        'notify_renewals' => 'boolean',
        'is_demo' => 'boolean',
        'is_group' => 'boolean',
        'type' => ProductType::class,
        'billing_cycle' => BillingCycle::class,
        'status' => ContractStatus::class,
        // Versleuteld opgeslagen in de database (encryptie via APP_KEY).
        'license_keys' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseBundle(): BelongsTo
    {
        return $this->belongsTo(PurchaseBundle::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Changes to what this contract's direct debit collects. */
    public function billingChanges(): HasMany
    {
        return $this->hasMany(ContractBillingChange::class);
    }

    public function pendingBillingChange(): ?ContractBillingChange
    {
        return $this->billingChanges()->pending()->latest('id')->first();
    }

    /** Whether a mandate is in place that an amount change would affect. */
    public function collectsByDirectDebit(): bool
    {
        return (bool) $this->auto_collect && filled($this->stripe_subscription_id);
    }

    // --- Collections -------------------------------------------------
    //
    // A contract is either a service or a collection of them. The
    // collection owns the term — cycle, start date, notice period,
    // auto-renew — and its services carry their own product and price.
    // One level only: a collection never holds another.

    /** The collection this service belongs to, if any. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** The services inside this collection. */
    public function services(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function isGroup(): bool
    {
        return (bool) $this->is_group;
    }

    public function isInGroup(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * The fields a collection decides on behalf of its services.
     *
     * Copied down rather than read through: every renewal query, reminder
     * and widget in the application already reads these columns, and a
     * service whose dates disagree with its collection would show up in the
     * wrong week. The form makes them read-only on a service so the copy
     * cannot drift.
     *
     * @var list<string>
     */
    public const GROUP_LED_FIELDS = [
        'billing_cycle', 'start_date', 'renewal_date',
        'notice_period_days', 'auto_renew', 'next_invoice_date',
    ];

    /** Pushes this collection's term onto every service inside it. */
    public function syncTermToServices(): int
    {
        if (! $this->isGroup()) {
            return 0;
        }

        $term = [];
        foreach (self::GROUP_LED_FIELDS as $field) {
            $term[$field] = $this->getAttribute($field);
        }

        $changed = 0;
        foreach ($this->services()->get() as $service) {
            $service->forceFill($term);
            if ($service->isDirty()) {
                $service->save();
                $changed++;
            }
        }

        return $changed;
    }

    /** Top level: collections and services that belong to none. */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * The rows that carry money.
     *
     * A collection has no price of its own — it totals its services — so
     * summing revenue over everything would count nothing twice only as
     * long as this scope is used for it.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('is_group', false);
    }

    /** Sale price per period: a collection is the sum of what it holds. */
    public function periodSalePrice(): float
    {
        if ($this->isGroup()) {
            return (float) $this->services->sum(
                fn (self $service) => (float) $service->sale_price * (int) $service->quantity
            );
        }

        return (float) $this->sale_price * (int) $this->quantity;
    }

    public function periodCostPrice(): float
    {
        return (float) $this->effective_cost;
    }

    // --- Portal helpers (no internal cost / margin / license keys) -----

    /**
     * Portal copy for renewal / billing period.
     * Monthly: soft renew message (no hard end date).
     * Yearly (and other fixed periods): show renewal/expiry date when present.
     */
    public function portalRenewalDescription(): string
    {
        $date = $this->renewal_date?->translatedFormat('M j, Y');

        return match ($this->billing_cycle) {
            BillingCycle::Monthly => __('Monthly service/license — renews every month'),
            BillingCycle::Quarterly => $date
                ? __('Renews: :date (quarterly)', ['date' => $date])
                : __('Billed quarterly'),
            BillingCycle::Yearly => $date
                ? __('Renews / ends: :date', ['date' => $date])
                : __('Yearly service/license'),
            BillingCycle::Once => $date
                ? __('Ends: :date', ['date' => $date])
                : __('One-time service'),
            default => $date ?? '—',
        };
    }

    /** Total sale price for the period, formatted for the portal. */
    public function portalSalePriceFormatted(): string
    {
        return Money::format($this->periodSalePrice(), $this->currency);
    }

    public function isActiveForPortal(): bool
    {
        return $this->status === ContractStatus::Active;
    }

    public function canRenew(): bool
    {
        return $this->billing_cycle !== BillingCycle::Once
            && in_array($this->status, [
                ContractStatus::Active,
                ContractStatus::Expired,
                ContractStatus::Pending,
            ], true);
    }

    public function canCancel(): bool
    {
        return $this->status === ContractStatus::Active;
    }

    /** Advance renewal and invoice dates by one cycle and set status to active. */
    public function renewPeriod(): void
    {
        if (! $this->canRenew()) {
            throw new \LogicException('This contract cannot be renewed.');
        }

        $anchor = $this->renewal_date ?? $this->start_date ?? now();
        $invoiceAnchor = $this->next_invoice_date ?? $anchor;

        $this->forceFill([
            'renewal_date' => $this->billing_cycle->addPeriod($anchor),
            'next_invoice_date' => $this->billing_cycle->addPeriod($invoiceAnchor),
            'status' => ContractStatus::Active,
            'cancelled_at' => null,
        ])->save();

        // Renewing the collection renews what is in it; the services are not
        // separately renewable, which is the point of grouping them.
        if ($this->isGroup()) {
            $this->services()->update(['status' => ContractStatus::Active->value, 'cancelled_at' => null]);
            $this->syncTermToServices();
        }
    }

    public function cancelNow(?\DateTimeInterface $at = null): void
    {
        if (! $this->canCancel()) {
            throw new \LogicException('This contract is already inactive.');
        }

        $at ??= now()->toDateString();

        $this->forceFill([
            'status' => ContractStatus::Cancelled,
            'cancelled_at' => $at,
            'auto_renew' => false,
        ])->save();

        // Cancelling the collection ends everything in it. A customer who
        // wants to drop one service instead takes that service out of the
        // collection first, or cancels it on its own.
        if ($this->isGroup()) {
            foreach ($this->services()->where('status', ContractStatus::Active->value)->get() as $service) {
                $service->forceFill([
                    'status' => ContractStatus::Cancelled,
                    'cancelled_at' => $at,
                    'auto_renew' => false,
                ])->save();
            }
        }
    }

    /**
     * Takes a service out of its collection and leaves it standing alone.
     *
     * It keeps the term it inherited: it was running on those dates, and
     * resetting them on the way out would quietly move a renewal.
     */
    public function detachFromGroup(): void
    {
        if (! $this->isInGroup()) {
            return;
        }

        $this->forceFill(['parent_id' => null])->save();
    }

    /**
     * Puts a service into a collection and hands it the collection's term.
     *
     * A collection cannot go inside another, and nothing can go inside
     * itself: one level is all this is meant to be.
     */
    public function moveIntoGroup(self $group): bool
    {
        if (! $group->isGroup() || $this->isGroup() || $this->is($group)) {
            return false;
        }

        if ($this->company_id !== $group->company_id) {
            return false;
        }

        $term = [];
        foreach (self::GROUP_LED_FIELDS as $field) {
            $term[$field] = $group->getAttribute($field);
        }

        $this->forceFill([...$term, 'parent_id' => $group->getKey()])->save();

        return true;
    }

    /** Customer mail only if both the company and this license have it enabled. */
    public function shouldNotifyCustomer(): bool
    {
        if (! $this->notify_renewals) {
            return false;
        }

        $this->loadMissing('company');

        return (bool) ($this->company?->notify_renewals ?? true);
    }

    /** Recurring contracts can be auto-collected; one-off (`once`) cannot. */
    public function canEnableAutoCollect(): bool
    {
        return $this->billing_cycle !== BillingCycle::Once;
    }

    /** Label for Stripe payment / auto-collect status (Filament + admin). */
    public function stripePaymentStatusLabel(): string
    {
        return match ($this->stripe_payment_status) {
            'pending' => 'Activating',
            'active' => 'Active',
            'past_due' => 'Payment failed',
            'cancelled' => 'Canceled',
            'incomplete' => 'Incomplete',
            default => filled($this->stripe_payment_status)
                ? (string) $this->stripe_payment_status
                : 'Off',
        };
    }

    /** Filament badge color for stripe_payment_status. */
    public function stripePaymentStatusColor(): string
    {
        return match ($this->stripe_payment_status) {
            'active' => 'success',
            'pending', 'incomplete' => 'warning',
            'past_due' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    // --- Computed values (not stored) -----------------------------

    protected function totalCost(): Attribute
    {
        return Attribute::get(fn () => $this->quantity * (float) $this->cost_price);
    }

    protected function totalSale(): Attribute
    {
        // A collection's figures are its services', so every derived number
        // — margin, ARR, the lot — comes out right without each of them
        // having to know that collections exist.
        return Attribute::get(fn () => $this->isGroup()
            ? $this->periodSalePrice()
            : $this->quantity * (float) $this->sale_price);
    }

    /** Does cost come from a shared purchase bundle? */
    protected function usesBundle(): Attribute
    {
        return Attribute::get(fn () => $this->purchase_bundle_id !== null);
    }

    /**
     * Effective cost (per billing cycle): allocated share of the purchase
     * bundle, otherwise fixed cost (quantity × cost price).
     */
    protected function effectiveCost(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isGroup()) {
                return $this->services->sum(fn (self $service) => (float) $service->effective_cost);
            }

            if ($this->purchase_bundle_id && $this->purchaseBundle) {
                return $this->purchaseBundle->allocatedCostForContract($this);
            }

            return $this->total_cost;
        });
    }

    /** Margin in euro (over the total quantity), based on effective cost. */
    protected function marginEur(): Attribute
    {
        return Attribute::get(fn () => $this->total_sale - $this->effective_cost);
    }

    /** Margin as a percentage of sale value. */
    protected function marginPct(): Attribute
    {
        return Attribute::get(function () {
            $sale = $this->total_sale;

            return $sale > 0 ? round(($this->margin_eur / $sale) * 100, 1) : 0.0;
        });
    }

    /** Annualised sale value (for ARR); one-off contracts do not count. */
    protected function annualRevenue(): Attribute
    {
        return Attribute::get(fn () => $this->total_sale * ($this->billing_cycle?->periodsPerYear() ?? 0));
    }

    /** Annualised margin (for recurring margin reporting). */
    protected function annualMargin(): Attribute
    {
        return Attribute::get(fn () => $this->margin_eur * ($this->billing_cycle?->periodsPerYear() ?? 0));
    }

    /**
     * Contracts whose renewal is worth seeing coming: active, dated, and on a
     * committed term. See BillingCycle::withRenewalTerm().
     *
     * Top level only. A service inside a collection renews when its
     * collection does, so listing it as well would warn twice about one
     * thing and hide the thing that is actually renewed.
     */
    public function scopeWithUpcomingRenewalTerm(Builder $query): Builder
    {
        return $query
            ->topLevel()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('renewal_date')
            ->whereIn('billing_cycle', BillingCycle::renewalTermValues());
    }

    /** Last date to cancel in order to prevent renewal. */
    protected function noticeDeadline(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->renewal_date) {
                return null;
            }

            return $this->renewal_date->copy()->subDays($this->notice_period_days);
        });
    }
}

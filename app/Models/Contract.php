<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use Auditable;

    protected $fillable = [
        'company_id', 'product_id', 'vendor_id', 'purchase_bundle_id',
        'name', 'reference', 'type',
        'quantity', 'cost_price', 'sale_price', 'currency', 'billing_cycle',
        'start_date', 'renewal_date', 'notice_period_days', 'auto_renew',
        'status', 'next_invoice_date', 'cancelled_at',
        'license_keys', 'notes', 'notify_renewals', 'is_demo',
        'auto_collect', 'stripe_subscription_id', 'stripe_subscription_item_id',
        'stripe_payment_method_id', 'stripe_mandate_id', 'stripe_payment_status',
        'auto_collect_enabled_at',
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
        'type' => \App\Enums\ProductType::class,
        'billing_cycle' => \App\Enums\BillingCycle::class,
        'status' => \App\Enums\ContractStatus::class,
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

    // --- Portal helpers (no internal cost / margin / license keys) -----

    /**
     * Portal copy for renewal / billing period.
     * Monthly: soft renew message (no hard end date).
     * Yearly (and other fixed periods): show renewal/expiry date when present.
     */
    public function portalRenewalDescription(): string
    {
        return match ($this->billing_cycle) {
            BillingCycle::Monthly => 'Monthly service/license — renews every month',
            BillingCycle::Quarterly => $this->renewal_date
                ? 'Renews: '.$this->renewal_date->format('M j, Y').' (quarterly)'
                : 'Billed quarterly',
            BillingCycle::Yearly => $this->renewal_date
                ? 'Renews / ends: '.$this->renewal_date->format('M j, Y')
                : 'Yearly service/license',
            BillingCycle::Once => $this->renewal_date
                ? 'Ends: '.$this->renewal_date->format('M j, Y')
                : 'One-time service',
            default => $this->renewal_date?->format('M j, Y') ?? '—',
        };
    }

    /** Unit sale price formatted for portal (EUR). */
    public function portalSalePriceFormatted(): string
    {
        $total = (float) $this->sale_price * (int) $this->quantity;

        return '€ '.number_format($total, 2);
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
    }

    public function cancelNow(?\DateTimeInterface $at = null): void
    {
        if (! $this->canCancel()) {
            throw new \LogicException('This contract is already inactive.');
        }

        $this->forceFill([
            'status' => ContractStatus::Cancelled,
            'cancelled_at' => $at ?? now()->toDateString(),
            'auto_renew' => false,
        ])->save();
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
        return Attribute::get(fn () => $this->quantity * (float) $this->sale_price);
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

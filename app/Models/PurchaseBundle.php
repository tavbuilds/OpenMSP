<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchased resell bundle with one fixed total cost (e.g. hosting).
 * Cost is split evenly across linked ACTIVE customer contracts.
 */
class PurchaseBundle extends Model
{
    use Auditable;

    protected $fillable = [
        'vendor_id', 'name', 'reference', 'total_cost', 'currency',
        'billing_cycle', 'allocation_method', 'start_date', 'renewal_date', 'notes', 'is_demo',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'start_date' => 'date',
        'renewal_date' => 'date',
        'billing_cycle' => \App\Enums\BillingCycle::class,
        'is_demo' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** Aantal actieve contracten waarover verdeeld wordt. */
    public function activeContractsCount(): int
    {
        return $this->contracts()
            ->where('status', ContractStatus::Active->value)
            ->count();
    }

    /** Annualised total cost of this bundle. */
    protected function annualCost(): Attribute
    {
        return Attribute::get(fn () => (float) $this->total_cost * ($this->billing_cycle?->periodsPerYear() ?? 0));
    }

    /** Allocated annual cost share per active contract (split evenly). */
    public function allocatedAnnualCostPerContract(): float
    {
        $count = $this->activeContractsCount();

        return $count > 0 ? $this->annual_cost / $count : 0.0;
    }

    /**
     * Allocated cost share for one contract, expressed in that contract’s
     * billing cycle (so it lines up with other rows).
     */
    public function allocatedCostForContract(Contract $contract): float
    {
        if ($contract->status !== ContractStatus::Active) {
            return 0.0;
        }

        $annualShare = $this->allocatedAnnualCostPerContract();
        $periods = $contract->billing_cycle?->periodsPerYear() ?? 0;

        // 'once' (0 periods) → show the full annual share.
        return $periods > 0 ? $annualShare / $periods : $annualShare;
    }

    /** Total annualised resale revenue of linked active contracts. */
    public function annualResale(): float
    {
        return $this->contracts()
            ->where('status', ContractStatus::Active->value)
            ->get()
            ->sum(fn (Contract $c) => $c->annual_revenue);
    }

    /** Recovery rate: how much of the cost is covered by resale revenue. */
    public function recoveryPercentage(): float
    {
        return $this->annual_cost > 0
            ? round(($this->annualResale() / $this->annual_cost) * 100, 1)
            : 0.0;
    }
}

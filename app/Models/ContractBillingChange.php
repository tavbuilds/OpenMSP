<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A move waiting on the customer, and what it would cost them.
 *
 * The mandate survives a change of amount — it was given to us, not to a
 * sum — so the subscription can be updated instead of cancelled and asked
 * for again. The sum is still theirs to agree to, and that agreement is
 * the last step: until it is given, the move written down here has not
 * happened. Nothing is added, nothing is taken out, and the direct debit
 * goes on collecting exactly what it did before.
 */
class ContractBillingChange extends Model
{
    use Auditable;

    public const REASON_SERVICE_ADDED = 'service_added';

    public const REASON_SERVICE_REMOVED = 'service_removed';

    protected $fillable = [
        'contract_id', 'company_id', 'service_id', 'service_name',
        'previous_amount', 'new_amount', 'currency',
        'reason', 'status', 'confirmed_at', 'declined_at',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'service_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** Taken back by the operator, rather than answered. */
    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAddition(): bool
    {
        return $this->reason === self::REASON_SERVICE_ADDED;
    }

    /**
     * What the mandate would collect if this were carried out now.
     *
     * Worked out from the contracts as they stand rather than read back
     * from the row, so an approval can be checked against the figure the
     * customer was actually shown.
     */
    public function amountAfterMove(): float
    {
        $group = $this->contract;
        $service = $this->service;

        if ($group === null || $service === null) {
            return (float) $this->new_amount;
        }

        if ($this->isAddition()) {
            return $group->periodSalePrice() + $service->periodSalePrice();
        }

        return $group->services()->count() === 1
            ? $service->periodSalePrice()
            : $group->periodSalePrice() - $service->periodSalePrice();
    }

    public function goesUp(): bool
    {
        return (float) $this->new_amount > (float) $this->previous_amount;
    }

    public function previousFormatted(): string
    {
        return Money::format((float) $this->previous_amount, $this->currency);
    }

    public function newFormatted(): string
    {
        return Money::format((float) $this->new_amount, $this->currency);
    }

    /** What is being asked, in the present tense: none of it has happened. */
    public function explanation(): string
    {
        $service = $this->service_name ?: __('a service');

        return $this->isAddition()
            ? __(':service is ready to be added to :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ])
            : __(':service is set to be taken out of :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ]);
    }

    /** What declining, or simply leaving it, means. */
    public function consequence(): string
    {
        $service = $this->service_name ?: __('the service');

        return $this->isAddition()
            ? __('Until you approve it, :service is not added and your direct debit stays at :previous.', [
                'service' => $service,
                'previous' => $this->previousFormatted(),
            ])
            : __('Until you approve it, :service stays in the contract and your direct debit stays at :previous.', [
                'service' => $service,
                'previous' => $this->previousFormatted(),
            ]);
    }
}

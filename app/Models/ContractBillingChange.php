<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change to what a direct debit collects, waiting on the customer.
 *
 * The mandate survives a change of amount — it was given to us, not to a
 * sum — so the subscription can be updated instead of cancelled and asked
 * for again. Collecting a different amount without warning cannot survive,
 * so it waits here and the old amount keeps running until it is answered.
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

    /** Retired by a later move, rather than answered. */
    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
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

    /** The sentence the customer reads before deciding. */
    public function explanation(): string
    {
        $service = $this->service_name ?: __('a service');

        return $this->reason === self::REASON_SERVICE_ADDED
            ? __(':service was added to :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ])
            : __(':service was taken out of :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ]);
    }
}

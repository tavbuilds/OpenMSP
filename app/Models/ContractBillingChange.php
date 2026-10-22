<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change to what a direct debit collects, after the fact.
 *
 * The mandate survives a change of amount — it was given to us, not to a
 * sum — so the subscription is simply re-priced. And an operator changing
 * a contract is carrying out what the customer asked for, so there is
 * nothing to approve. What is owed is the telling, and this is the record
 * of what was changed and what they were told.
 */
class ContractBillingChange extends Model
{
    use Auditable;

    public const REASON_SERVICE_ADDED = 'service_added';

    public const REASON_SERVICE_REMOVED = 'service_removed';

    public const REASON_AMOUNT_EDITED = 'amount_edited';

    protected $fillable = [
        'contract_id', 'company_id', 'service_id', 'service_name',
        'previous_amount', 'new_amount', 'currency', 'reason', 'notified_at',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'notified_at' => 'datetime',
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

    /** What happened, in the past tense, because it has. */
    public function explanation(): string
    {
        $service = $this->service_name ?: __('a service');

        return match ($this->reason) {
            self::REASON_SERVICE_ADDED => __(':service was added to :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ]),
            self::REASON_SERVICE_REMOVED => __(':service was taken out of :contract.', [
                'service' => $service,
                'contract' => $this->contract?->name,
            ]),
            default => __('What :contract covers was changed.', [
                'contract' => $this->contract?->name,
            ]),
        };
    }
}

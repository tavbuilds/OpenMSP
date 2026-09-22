<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'company_id',
        'contract_id',
        'stripe_invoice_id',
        'number',
        'amount_due',
        'amount_paid',
        'currency',
        'status',
        'hosted_invoice_url',
        'invoice_pdf',
        'stripe_created_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'stripe_created_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** Stripe reports amounts in minor units; show them like every other price. */
    public function amountPaidFormatted(): string
    {
        return Money::formatMinor($this->amount_paid, $this->currency);
    }

    public function amountDueFormatted(): string
    {
        return Money::formatMinor($this->amount_due, $this->currency);
    }

    /** The amount a customer cares about: what was paid, or what is still owed. */
    public function displayAmountFormatted(): string
    {
        return $this->status === 'paid'
            ? $this->amountPaidFormatted()
            : $this->amountDueFormatted();
    }

    /** A human label for the invoice: its number, or a dated fallback for drafts. */
    public function displayNumber(): string
    {
        if (filled($this->number)) {
            return $this->number;
        }

        return $this->stripe_created_at
            ? __('Invoice of :date', ['date' => $this->stripe_created_at->translatedFormat('M j, Y')])
            : __('Draft invoice');
    }

    /** Badge colour class suffix for the portal status pill. */
    public function statusTone(): string
    {
        return match ($this->status) {
            'paid' => 'ok',
            'open', 'draft' => 'pending',
            'uncollectible', 'void' => 'err',
            default => 'neutral',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => __('Paid'),
            'open' => __('Open'),
            'draft' => __('Draft'),
            'uncollectible' => __('Uncollectible'),
            'void' => __('Void'),
            default => ucfirst((string) $this->status),
        };
    }
}

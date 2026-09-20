<?php

namespace App\Models;

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

    /** Display amount in major units (e.g. euros). */
    public function amountPaidFormatted(): string
    {
        $major = $this->amount_paid / 100;

        return strtoupper($this->currency).' '.number_format($major, 2);
    }

    public function amountDueFormatted(): string
    {
        $major = $this->amount_due / 100;

        return strtoupper($this->currency).' '.number_format($major, 2);
    }
}

<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceOption extends Model
{
    protected $fillable = [
        'product_id', 'billing_term', 'commitment_term', 'commitment_months',
        'billing_cycle', 'pricing_type', 'unit_of_measure', 'charge_type',
        'min_qty', 'max_qty', 'cost_price', 'sale_price', 'currency', 'is_default',
    ];

    protected $casts = [
        'commitment_months' => 'integer',
        'min_qty' => 'integer',
        'max_qty' => 'integer',
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'is_default' => 'boolean',
        'billing_cycle' => BillingCycle::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function qtyLabel(): string
    {
        $min = max(1, (int) $this->min_qty);
        $max = $this->max_qty;

        return $max ? $min.'–'.$max : $min.'+';
    }

    public function label(): string
    {
        return trim($this->commitment_term.' / '.$this->billing_term);
    }
}

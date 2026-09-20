<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComponent extends Model
{
    protected $fillable = ['product_id', 'component_id', 'quantity'];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /** The composed product (bundle). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** The component (itself also a catalog product). */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }
}

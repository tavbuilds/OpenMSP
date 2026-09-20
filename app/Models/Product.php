<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'vendor_id', 'name', 'sku', 'type',
        'default_cost_price', 'default_sale_price',
        'currency', 'billing_cycle', 'description', 'active', 'is_demo',
    ];

    protected $casts = [
        'default_cost_price' => 'decimal:2',
        'default_sale_price' => 'decimal:2',
        'active' => 'boolean',
        'is_demo' => 'boolean',
        'type' => \App\Enums\ProductType::class,
        'billing_cycle' => \App\Enums\BillingCycle::class,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    // --- Samenstelling (stuklijst) --------------------------------------

    /** De onderdelen (catalog products) waaruit dit pakket is opgebouwd. */
    public function componentLinks(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'product_id');
    }

    /** Components as a product relation, including quantity via the pivot. */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'product_id', 'component_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /** Bundles waarin dit product als onderdeel is opgenomen. */
    public function partOfProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'component_id', 'product_id')
            ->withPivot('quantity');
    }

    public function isComposite(): bool
    {
        return $this->componentLinks()->exists();
    }

    // --- Computed prices & margin --------------------------------------

    /**
     * Effective cost price: for a composed product the sum of components
     * (× quantity), otherwise the fixed cost price. Because this is computed,
     * a price change on a component flows through to every bundle that uses it.
     */
    protected function effectiveCostPrice(): Attribute
    {
        return Attribute::get(fn () => $this->computeEffectiveCost());
    }

    /** Recursive sum of components; $seen prevents infinite loops. */
    public function computeEffectiveCost(array $seen = []): float
    {
        if (in_array($this->id, $seen, true)) {
            return 0.0; // circular reference — ignore
        }

        $seen[] = $this->id;
        $components = $this->components;

        if ($components->isEmpty()) {
            return (float) $this->default_cost_price;
        }

        return $components->sum(fn (Product $c) => $c->computeEffectiveCost($seen) * (int) $c->pivot->quantity);
    }

    protected function marginEur(): Attribute
    {
        return Attribute::get(fn () => (float) $this->default_sale_price - $this->effective_cost_price);
    }

    protected function marginPct(): Attribute
    {
        return Attribute::get(function () {
            $sale = (float) $this->default_sale_price;

            return $sale > 0 ? round(($this->margin_eur / $sale) * 100, 1) : 0.0;
        });
    }
}

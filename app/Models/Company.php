<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use Auditable;

    protected $casts = [
        'notify_renewals' => 'boolean',
        'is_demo' => 'boolean',
    ];

    protected $fillable = [
        'name', 'kvk_number', 'vat_number', 'email', 'phone',
        'address', 'postal_code', 'city', 'country', 'notes',
        'stripe_customer_id', 'notify_renewals', 'is_demo',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }

    // --- Key figures over de actieve contracten -------------------------

    /** @return \Illuminate\Support\Collection<int, Contract> */
    protected function activeContracts()
    {
        return $this->contracts()
            ->where('status', ContractStatus::Active->value)
            ->get();
    }

    /** Maandelijkse recurring omzet (MRR) over actieve contracten. */
    public function mrr(): float
    {
        return $this->activeContracts()->sum(fn (Contract $c) => $c->annual_revenue) / 12;
    }

    /** Geannualiseerde marge over actieve contracten. */
    public function annualMargin(): float
    {
        return $this->activeContracts()->sum(fn (Contract $c) => $c->annual_margin);
    }
}

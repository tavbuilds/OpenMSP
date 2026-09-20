<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_renew_shifts_yearly_dates_and_reactivates(): void
    {
        $company = Company::create(['name' => 'Renew Co', 'country' => 'NL']);
        $contract = Contract::create([
            'company_id' => $company->id,
            'name' => 'Yearly License',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 40,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2025-01-15',
            'renewal_date' => '2026-01-15',
            'next_invoice_date' => '2026-01-15',
            'status' => ContractStatus::Expired->value,
        ]);

        $contract->renewPeriod();
        $contract->refresh();

        $this->assertSame(ContractStatus::Active, $contract->status);
        $this->assertSame('2027-01-15', $contract->renewal_date->toDateString());
        $this->assertSame('2027-01-15', $contract->next_invoice_date->toDateString());
        $this->assertNull($contract->cancelled_at);
    }

    public function test_cancel_sets_status_and_stops_auto_renew(): void
    {
        $company = Company::create(['name' => 'Cancel Co', 'country' => 'NL']);
        $contract = Contract::create([
            'company_id' => $company->id,
            'name' => 'Active License',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 40,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Monthly->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addMonth()->toDateString(),
            'status' => ContractStatus::Active->value,
            'auto_renew' => true,
        ]);

        $contract->cancelNow();
        $contract->refresh();

        $this->assertSame(ContractStatus::Cancelled, $contract->status);
        $this->assertFalse($contract->auto_renew);
        $this->assertNotNull($contract->cancelled_at);
        $this->assertFalse($contract->canCancel());
    }

    public function test_once_cannot_be_renewed(): void
    {
        $company = Company::create(['name' => 'Once Co', 'country' => 'NL']);
        $contract = Contract::create([
            'company_id' => $company->id,
            'name' => 'Project',
            'type' => 'service',
            'quantity' => 1,
            'cost_price' => 100,
            'sale_price' => 250,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Once->value,
            'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active->value,
        ]);

        $this->assertFalse($contract->canRenew());
        $this->expectException(\LogicException::class);
        $contract->renewPeriod();
    }
}

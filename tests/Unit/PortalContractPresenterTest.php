<?php

namespace Tests\Unit;

use App\Enums\BillingCycle;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalContractPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_and_yearly_portal_copy(): void
    {
        $company = Company::create(['name' => 'Unit Co', 'country' => 'NL']);

        $monthly = Contract::create([
            'company_id' => $company->id,
            'name' => 'M',
            'type' => 'subscription',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Monthly->value,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $yearly = Contract::create([
            'company_id' => $company->id,
            'name' => 'Y',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2026-01-01',
            'renewal_date' => '2026-12-31',
            'status' => 'active',
        ]);

        $this->assertStringContainsString('renews every month', $monthly->portalRenewalDescription());
        $this->assertStringContainsString('Dec 31, 2026', $yearly->portalRenewalDescription());
    }

    public function test_auto_collect_eligibility_and_status_labels(): void
    {
        $company = Company::create(['name' => 'Unit Co', 'country' => 'NL']);

        $yearly = Contract::create([
            'company_id' => $company->id,
            'name' => 'Y',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $once = Contract::create([
            'company_id' => $company->id,
            'name' => 'O',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Once->value,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->assertTrue($yearly->canEnableAutoCollect());
        $this->assertFalse($once->canEnableAutoCollect());
        $this->assertSame('Off', $yearly->stripePaymentStatusLabel());
        $this->assertSame('gray', $yearly->stripePaymentStatusColor());

        $yearly->forceFill(['stripe_payment_status' => 'active', 'auto_collect' => true])->save();
        $yearly->refresh();
        $this->assertSame('Active', $yearly->stripePaymentStatusLabel());
        $this->assertSame('success', $yearly->stripePaymentStatusColor());

        $yearly->forceFill(['stripe_payment_status' => 'past_due'])->save();
        $yearly->refresh();
        $this->assertSame('Payment failed', $yearly->stripePaymentStatusLabel());
        $this->assertSame('danger', $yearly->stripePaymentStatusColor());
    }
}

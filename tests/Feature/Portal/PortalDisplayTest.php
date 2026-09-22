<?php

namespace Tests\Feature\Portal;

use App\Enums\BillingCycle;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_vs_yearly_portal_renewal_copy(): void
    {
        $company = Company::create(['name' => 'Display Co', 'country' => 'NL']);

        $yearly = Contract::create([
            'company_id' => $company->id,
            'name' => 'Yearly Thing',
            'type' => 'license',
            'quantity' => 2,
            'cost_price' => 10,
            'sale_price' => 40,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => '2026-01-01',
            'renewal_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $monthly = Contract::create([
            'company_id' => $company->id,
            'name' => 'Monthly Thing',
            'type' => 'subscription',
            'quantity' => 1,
            'cost_price' => 5,
            'sale_price' => 25,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Monthly->value,
            'start_date' => '2026-01-01',
            'renewal_date' => '2026-02-01',
            'status' => 'active',
        ]);

        $this->assertStringContainsString('Jan 1, 2027', $yearly->portalRenewalDescription());
        $this->assertStringContainsString('Monthly service/license', $monthly->portalRenewalDescription());
        $this->assertStringContainsString('renews every month', $monthly->portalRenewalDescription());

        // One money format across the portal, the admin pages and the
        // dashboard stats; Filament's own money() columns render the same way.
        $this->assertSame('€80.00', $yearly->portalSalePriceFormatted());
        $this->assertSame('€25.00', $monthly->portalSalePriceFormatted());
    }

    public function test_portal_invoices_are_scoped_to_company(): void
    {
        $companyA = Company::create(['name' => 'Inv A', 'country' => 'NL']);
        $companyB = Company::create(['name' => 'Inv B', 'country' => 'NL']);

        $contact = \App\Models\Contact::create([
            'company_id' => $companyA->id,
            'name' => 'Inv User',
            'email' => 'inv@a.example',
        ]);

        \App\Models\Invoice::create([
            'company_id' => $companyA->id,
            'stripe_invoice_id' => 'in_a_1',
            'number' => 'INV-A-1',
            'amount_due' => 5000,
            'amount_paid' => 5000,
            'currency' => 'eur',
            'status' => 'paid',
            'invoice_pdf' => 'https://example.com/a.pdf',
        ]);

        \App\Models\Invoice::create([
            'company_id' => $companyB->id,
            'stripe_invoice_id' => 'in_b_1',
            'number' => 'INV-B-SECRET',
            'amount_due' => 9000,
            'amount_paid' => 9000,
            'currency' => 'eur',
            'status' => 'paid',
            'invoice_pdf' => 'https://example.com/b.pdf',
        ]);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.invoices'))
            ->assertOk()
            ->assertSee('INV-A-1')
            ->assertDontSee('INV-B-SECRET');
    }

    public function test_portal_pages_are_mobile_ready(): void
    {
        $login = $this->get(route('portal.login'))->assertOk();
        $login->assertSee('width=device-width', false);
        $login->assertSee('viewport-fit=cover', false);
        $login->assertSee('font-size:16px', false);

        $company = Company::create(['name' => 'Mobile Co', 'country' => 'NL']);
        $contact = \App\Models\Contact::create([
            'company_id' => $company->id,
            'name' => 'Mobile User',
            'email' => 'mobile@co.example',
        ]);
        Contract::create([
            'company_id' => $company->id,
            'name' => 'Mobile License',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 50,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);

        $dash = $this->actingAs($contact, 'portal')->get(route('portal.dashboard'))->assertOk();
        $dash->assertSee('stack-card', false);
        $dash->assertSee('Mobile License');
        $dash->assertSee('min-height: 2.75rem', false);
    }
}

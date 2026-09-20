<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractRenewalReminder;
use App\Notifications\CustomerContractReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RenewalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_mail_respects_company_and_contract_flags(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $companyOn = Company::create(['name' => 'Mail On', 'country' => 'NL', 'notify_renewals' => true]);
        $contactOn = Contact::create([
            'company_id' => $companyOn->id,
            'name' => 'On User',
            'email' => 'on@example.com',
        ]);
        $contractOn = Contract::create([
            'company_id' => $companyOn->id,
            'name' => 'Licentie Aan',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addDays(7)->toDateString(),
            'notice_period_days' => 0,
            'status' => ContractStatus::Active->value,
            'notify_renewals' => true,
        ]);

        $companyOff = Company::create(['name' => 'Mail Off', 'country' => 'NL', 'notify_renewals' => false]);
        $contactOff = Contact::create([
            'company_id' => $companyOff->id,
            'name' => 'Off User',
            'email' => 'off@example.com',
        ]);
        Contract::create([
            'company_id' => $companyOff->id,
            'name' => 'Licentie Uit Klant',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addDays(7)->toDateString(),
            'notice_period_days' => 0,
            'status' => ContractStatus::Active->value,
            'notify_renewals' => true,
        ]);

        $contractMuted = Contract::create([
            'company_id' => $companyOn->id,
            'name' => 'Licentie Uit',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 10,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly->value,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addDays(7)->toDateString(),
            'notice_period_days' => 0,
            'status' => ContractStatus::Active->value,
            'notify_renewals' => false,
        ]);

        $this->artisan('contracts:send-renewal-reminders')->assertSuccessful();

        Notification::assertSentTo($contactOn, CustomerContractReminder::class);
        Notification::assertNotSentTo($contactOff, CustomerContractReminder::class);
        $this->assertFalse($contractMuted->fresh()->shouldNotifyCustomer());
        $this->assertTrue($contractOn->fresh()->shouldNotifyCustomer());
        Notification::assertSentTo($admin, ContractRenewalReminder::class);
    }
}

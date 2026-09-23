<?php

namespace App\Support;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\EndpointKind;
use App\Enums\EndpointSource;
use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskPriority;
use App\Enums\PlannedTaskStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Domain;
use App\Models\Endpoint;
use App\Models\Invoice;
use App\Models\PlannedTask;
use App\Models\Product;
use App\Models\PurchaseBundle;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

final class DemoData
{
    public static function exists(): bool
    {
        return Company::query()->where('is_demo', true)->exists()
            || Vendor::query()->where('is_demo', true)->exists()
            || Domain::query()->where('is_demo', true)->exists()
            || Endpoint::query()->where('is_demo', true)->exists()
            || PlannedTask::query()->where('is_demo', true)->exists();
    }

    public static function seed(): void
    {
        if (self::exists()) {
            return;
        }

        DB::transaction(function (): void {
            $contoso = Vendor::create([
                'name' => 'Contoso Cloud',
                'website' => 'https://example.com/contoso',
                'email' => 'partner@contoso.example',
                'is_demo' => true,
            ]);
            $acme = Vendor::create([
                'name' => 'Acme Backup',
                'website' => 'https://example.com/acme',
                'is_demo' => true,
            ]);

            $m365 = Product::create([
                'vendor_id' => $contoso->id,
                'name' => 'Microsoft 365 Business Standard',
                'sku' => 'M365-BS',
                'type' => ProductType::License,
                'default_cost_price' => 8.80,
                'default_sale_price' => 12.60,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Monthly,
                'active' => true,
                'is_demo' => true,
            ]);
            $acronis = Product::create([
                'vendor_id' => $acme->id,
                'name' => 'Acronis Backup 500 GB',
                'sku' => 'ACR-500',
                'type' => ProductType::License,
                'default_cost_price' => 9.00,
                'default_sale_price' => 19.00,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Yearly,
                'active' => true,
                'is_demo' => true,
            ]);
            $fw = Product::create([
                'vendor_id' => $contoso->id,
                'name' => 'Managed firewall',
                'sku' => 'FW-MGD',
                'type' => ProductType::Service,
                'default_cost_price' => 40.00,
                'default_sale_price' => 79.00,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Monthly,
                'active' => true,
                'is_demo' => true,
            ]);

            $bundle = PurchaseBundle::create([
                'vendor_id' => $contoso->id,
                'name' => 'Hosting bundle (shared cost)',
                'reference' => 'HOST-PACK',
                'total_cost' => 240.00,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Yearly,
                'allocation_method' => 'even',
                'start_date' => now()->subMonths(6)->toDateString(),
                'renewal_date' => now()->addMonths(6)->toDateString(),
                'is_demo' => true,
            ]);

            $bakkerij = Company::create([
                'name' => 'Bakkerij De Zon',
                'email' => 'info@dezondemo.example',
                'city' => 'Utrecht',
                'country' => 'NL',
                'notify_renewals' => true,
                'is_demo' => true,
            ]);
            $gemeente = Company::create([
                'name' => 'Gemeente Linden',
                'email' => 'ict@linden-demo.example',
                'city' => 'Linden',
                'country' => 'NL',
                'notify_renewals' => true,
                'is_demo' => true,
            ]);
            $studio = Company::create([
                'name' => 'Studio Noord',
                'email' => 'hello@studionoord-demo.example',
                'city' => 'Groningen',
                'country' => 'NL',
                'notify_renewals' => false,
                'is_demo' => true,
            ]);

            Contact::create([
                'company_id' => $bakkerij->id,
                'name' => 'Anna Bakker',
                'email' => 'anna@dezondemo.example',
                'job_title' => 'Eigenaar',
                'is_primary' => true,
                'is_demo' => true,
            ]);
            Contact::create([
                'company_id' => $gemeente->id,
                'name' => 'Piet ICT',
                'email' => 'piet@linden-demo.example',
                'job_title' => 'Functional administrator',
                'is_primary' => true,
                'is_demo' => true,
            ]);
            Contact::create([
                'company_id' => $studio->id,
                'name' => 'Noor Design',
                'email' => 'noor@studionoord-demo.example',
                'is_primary' => true,
                'is_demo' => true,
            ]);

            Contract::create([
                'company_id' => $bakkerij->id,
                'product_id' => $m365->id,
                'vendor_id' => $contoso->id,
                'name' => 'Microsoft 365 Business Standard',
                'type' => ProductType::License,
                'quantity' => 8,
                'cost_price' => 8.80,
                'sale_price' => 12.60,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Monthly,
                'start_date' => now()->subMonths(4)->toDateString(),
                'renewal_date' => now()->addDays(12)->toDateString(),
                'notice_period_days' => 7,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
                'next_invoice_date' => now()->addDays(12)->toDateString(),
                'notify_renewals' => true,
                'is_demo' => true,
            ]);

            Contract::create([
                'company_id' => $gemeente->id,
                'product_id' => $acronis->id,
                'vendor_id' => $acme->id,
                'name' => 'Acronis Backup 500 GB',
                'type' => ProductType::License,
                'quantity' => 3,
                'cost_price' => 9.00,
                'sale_price' => 19.00,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Yearly,
                'start_date' => now()->subMonths(11)->toDateString(),
                'renewal_date' => now()->addDays(40)->toDateString(),
                'notice_period_days' => 14,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
                'notify_renewals' => true,
                'is_demo' => true,
            ]);

            Contract::create([
                'company_id' => $gemeente->id,
                'product_id' => $fw->id,
                'vendor_id' => $contoso->id,
                'purchase_bundle_id' => $bundle->id,
                'name' => 'Managed firewall',
                'type' => ProductType::Service,
                'quantity' => 1,
                'cost_price' => 0,
                'sale_price' => 79.00,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Monthly,
                'start_date' => now()->subMonths(2)->toDateString(),
                'renewal_date' => now()->addDays(20)->toDateString(),
                'notice_period_days' => 14,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
                'auto_collect' => true,
                'stripe_payment_status' => 'past_due',
                'notify_renewals' => true,
                'is_demo' => true,
            ]);

            Contract::create([
                'company_id' => $studio->id,
                'product_id' => $m365->id,
                'vendor_id' => $contoso->id,
                'name' => 'Microsoft 365 Business Standard',
                'type' => ProductType::License,
                'quantity' => 4,
                'cost_price' => 8.80,
                'sale_price' => 12.60,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Monthly,
                'start_date' => now()->subYear()->toDateString(),
                'renewal_date' => now()->addDays(20)->toDateString(),
                'notice_period_days' => 30,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
                'notify_renewals' => false,
                'is_demo' => true,
            ]);

            // Domains are not contracts: no quantity, no sale price of their
            // own, and the customer link is made here rather than imported.
            $registrar = Vendor::create([
                'name' => 'Demo Registrar',
                'website' => 'https://example.com/registrar',
                'is_demo' => true,
            ]);
            $tldNl = Product::create([
                'vendor_id' => $registrar->id,
                'name' => '.example domain',
                'sku' => 'tld-example',
                'type' => ProductType::Service,
                'default_cost_price' => 6.50,
                'default_sale_price' => 14.95,
                'currency' => 'EUR',
                'billing_cycle' => BillingCycle::Yearly,
                'active' => true,
                'is_demo' => true,
            ]);

            Domain::create([
                'company_id' => $bakkerij->id,
                'product_id' => $tldNl->id,
                'name' => 'dezondemo.example',
                'expires_at' => now()->addDays(24)->toDateString(),
                'renewal_date' => now()->addDays(24)->toDateString(),
                'auto_renew' => true,
                'status' => 'ACT',
                'notes' => 'Renewal invoice goes to the bakery’s accountant.',
                'notes_visible_to_customer' => true,
                'is_demo' => true,
            ]);
            Domain::create([
                'company_id' => $gemeente->id,
                'product_id' => $tldNl->id,
                'name' => 'linden-demo.example',
                'expires_at' => now()->addDays(52)->toDateString(),
                'renewal_date' => now()->addDays(52)->toDateString(),
                'auto_renew' => true,
                'status' => 'ACT',
                'notes' => 'Transfer away after the new website goes live.',
                'is_demo' => true,
            ]);
            Domain::create([
                'company_id' => $studio->id,
                'product_id' => $tldNl->id,
                'name' => 'studionoord-demo.example',
                'expires_at' => now()->addDays(9)->toDateString(),
                'renewal_date' => now()->addDays(9)->toDateString(),
                'auto_renew' => false,
                'status' => 'ACT',
                'is_demo' => true,
            ]);
            // Freshly imported and still waiting for someone to say whose it is.
            Domain::create([
                'product_id' => $tldNl->id,
                'name' => 'oudproject-demo.example',
                'expires_at' => now()->addDays(38)->toDateString(),
                'renewal_date' => now()->addDays(38)->toDateString(),
                'auto_renew' => false,
                'status' => 'ACT',
                'is_demo' => true,
            ]);

            Endpoint::create([
                'company_id' => $bakkerij->id,
                'name' => 'web.dezondemo.example',
                'kind' => EndpointKind::Certificate,
                'hostname' => 'web.dezondemo.example',
                'url' => 'https://web.dezondemo.example',
                'expires_at' => now()->addDays(12)->toDateString(),
                'source' => EndpointSource::Webhook,
                'last_status' => 'warning',
                'notify_customer' => true,
                'is_demo' => true,
            ]);
            Endpoint::create([
                'company_id' => $gemeente->id,
                'name' => 'mail.linden-demo.example',
                'kind' => EndpointKind::Certificate,
                'hostname' => 'mail.linden-demo.example',
                'expires_at' => now()->subDays(3)->toDateString(),
                'source' => EndpointSource::Manual,
                'last_status' => 'expired',
                'is_demo' => true,
            ]);
            Endpoint::create([
                'company_id' => $studio->id,
                'name' => 'vpn.studionoord-demo.example',
                'kind' => EndpointKind::Certificate,
                'hostname' => 'vpn.studionoord-demo.example',
                'expires_at' => now()->addDays(40)->toDateString(),
                'source' => EndpointSource::Webhook,
                'notify_30' => false,
                'last_status' => 'ok',
                'is_demo' => true,
            ]);
            Endpoint::create([
                'name' => 'Intern wildcard *.msp.example',
                'kind' => EndpointKind::Certificate,
                'hostname' => '*.msp.example',
                'expires_at' => now()->addDays(7)->toDateString(),
                'source' => EndpointSource::Manual,
                'last_status' => 'warning',
                'is_demo' => true,
            ]);

            PlannedTask::create([
                'company_id' => $bakkerij->id,
                'title' => 'Bakery shop move',
                'kind' => PlannedTaskKind::Relocation,
                'status' => PlannedTaskStatus::Planned,
                'priority' => PlannedTaskPriority::High,
                'due_on' => now()->addDays(18)->toDateString(),
                'location_from' => 'Oudegracht 12, Utrecht',
                'location_to' => 'Leidseweg 8, Utrecht',
                'notes' => 'Move POS, Wi-Fi, and the back-office PC. Confirm ISP lead time.',
                'is_demo' => true,
            ]);
            PlannedTask::create([
                'company_id' => $gemeente->id,
                'title' => 'Microsoft 365 tenant migration',
                'kind' => PlannedTaskKind::Migration,
                'status' => PlannedTaskStatus::InProgress,
                'priority' => PlannedTaskPriority::Urgent,
                'due_on' => now()->addDays(40)->toDateString(),
                'notes' => 'Cut over mailboxes after the backup contract renewal.',
                'is_demo' => true,
            ]);
            PlannedTask::create([
                'company_id' => $studio->id,
                'title' => 'Firewall cutover on-site',
                'kind' => PlannedTaskKind::Onsite,
                'status' => PlannedTaskStatus::Planned,
                'priority' => PlannedTaskPriority::Normal,
                'due_on' => now()->addDays(6)->toDateString(),
                'location_from' => 'Studio Noord server closet',
                'notes' => 'Swap the managed firewall after hours.',
                'is_demo' => true,
            ]);
            PlannedTask::create([
                'title' => 'Internal lab hypervisor rebuild',
                'kind' => PlannedTaskKind::Project,
                'status' => PlannedTaskStatus::Blocked,
                'priority' => PlannedTaskPriority::Low,
                'due_on' => now()->subDays(4)->toDateString(),
                'notes' => 'Waiting on replacement disks. Internal, no customer.',
                'is_demo' => true,
            ]);
        });
    }

    public static function purge(): int
    {
        return (int) DB::transaction(function (): int {
            $companyIds = Company::query()->where('is_demo', true)->pluck('id');
            $contractIds = Contract::query()->where('is_demo', true)->pluck('id');
            $productIds = Product::query()->where('is_demo', true)->pluck('id');

            $deleted = 0;

            if ($contractIds->isNotEmpty()) {
                $deleted += Invoice::query()->whereIn('contract_id', $contractIds)->delete();
            }
            if ($companyIds->isNotEmpty()) {
                $deleted += Invoice::query()->whereIn('company_id', $companyIds)->delete();
            }

            $deleted += PlannedTask::query()->where('is_demo', true)->delete();
            $deleted += Domain::query()->where('is_demo', true)->delete();
            $deleted += Endpoint::query()->where('is_demo', true)->delete();
            $deleted += Contract::query()->where('is_demo', true)->delete();
            $deleted += Contact::query()->where('is_demo', true)->delete();

            if ($productIds->isNotEmpty()) {
                DB::table('product_components')->whereIn('product_id', $productIds)->delete();
                DB::table('product_components')->whereIn('component_id', $productIds)->delete();
            }

            $deleted += PurchaseBundle::query()->where('is_demo', true)->delete();
            $deleted += Product::query()->where('is_demo', true)->delete();
            $deleted += Vendor::query()->where('is_demo', true)->delete();
            $deleted += Company::query()->where('is_demo', true)->delete();

            DemoAccount::purge();

            return $deleted;
        });
    }
}

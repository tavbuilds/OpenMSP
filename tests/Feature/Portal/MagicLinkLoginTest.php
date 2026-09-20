<?php

namespace Tests\Feature\Portal;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Notifications\PortalMagicLink;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicLinkLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_login_scopes_queries_to_contact_company(): void
    {
        Notification::fake();

        $companyA = Company::create(['name' => 'Alpha BV', 'country' => 'NL']);
        $companyB = Company::create(['name' => 'Beta BV', 'country' => 'NL']);

        $contact = Contact::create([
            'company_id' => $companyA->id,
            'name' => 'Ada Portal',
            'email' => 'ada@alpha.example',
        ]);

        Contract::create([
            'company_id' => $companyA->id,
            'name' => 'Alpha License',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 50,
            'currency' => 'EUR',
            'billing_cycle' => 'yearly',
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);

        Contract::create([
            'company_id' => $companyB->id,
            'name' => 'Beta Secret',
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 99,
            'sale_price' => 199,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->post(route('portal.login.request'), ['email' => 'ada@alpha.example'])
            ->assertRedirect();

        $sentUrl = null;
        Notification::assertSentTo($contact, PortalMagicLink::class, function (PortalMagicLink $n) use (&$sentUrl) {
            $sentUrl = $n->url;

            return str_contains($n->url, '/portal/magic/');
        });

        $this->assertNotNull($sentUrl);

        $this->get($sentUrl)->assertRedirect(route('portal.dashboard'));

        $dash = $this->get(route('portal.dashboard'));
        $dash->assertOk();
        $dash->assertSee('Alpha License');
        $dash->assertDontSee('Beta Secret');
        // Internal cost / margin must not appear in portal HTML
        $dash->assertDontSee('Inkoop');
        $dash->assertDontSee('Marge');
    }

    public function test_portal_cannot_see_other_company_contract_auto_collect_route(): void
    {
        $companyA = Company::create(['name' => 'A Co', 'country' => 'NL']);
        $companyB = Company::create(['name' => 'B Co', 'country' => 'NL']);

        $contact = Contact::create([
            'company_id' => $companyA->id,
            'name' => 'User A',
            'email' => 'a@example.com',
        ]);

        $foreign = Contract::create([
            'company_id' => $companyB->id,
            'name' => 'Foreign',
            'type' => 'service',
            'quantity' => 1,
            'cost_price' => 1,
            'sale_price' => 100,
            'currency' => 'EUR',
            'billing_cycle' => 'yearly',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.auto-collect', $foreign))
            ->assertNotFound();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $company = Company::create(['name' => 'Sig Co', 'country' => 'NL']);
        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Sig',
            'email' => 'sig@example.com',
        ]);

        // Missing/invalid signature → Laravel signed middleware → 403
        $this->get(route('portal.magic', ['contact' => $contact->id]))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_unknown_email_does_not_reveal_existence(): void
    {
        Notification::fake();

        $this->post(route('portal.login.request'), ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_magic_link_mail_uses_platform_name(): void
    {
        PlatformSettings::set(PlatformSettings::NAME, 'Acme MSP');

        $mail = (new PortalMagicLink('https://example.test/portal/magic/1'))
            ->toMail((object) ['name' => 'Ada']);

        $this->assertSame('Sign in to the customer portal — Acme MSP', $mail->subject);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\RelationManagers\ContractsRelationManager;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\PortfolioStats;
use App\Filament\Widgets\UpcomingRenewals;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\User;
use App\Support\ContractGroups;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * A contract used to be one service, so a customer with hosting, a domain
 * and a certificate had three agreements where they had bought one. A
 * contract can now hold services: it owns the term, they keep their prices.
 */
class ContractGroupTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Klant BV', 'country' => 'NL']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function service(string $name, array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'company_id' => $this->company->id,
            'name' => $name,
            'type' => 'license',
            'quantity' => 1,
            'cost_price' => 10,
            'sale_price' => 25,
            'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly,
            'start_date' => now()->subYear()->toDateString(),
            'renewal_date' => now()->addDays(40)->toDateString(),
            'notice_period_days' => 30,
            'status' => ContractStatus::Active,
        ], $overrides));
    }

    /**
     * @param  list<Contract>  $services
     * @param  array<string, mixed>  $term
     */
    private function merge(array $services, array $term = []): Contract
    {
        return ContractGroups::merge(new Collection($services), array_merge([
            'name' => 'Website hosting & domains',
            'billing_cycle' => BillingCycle::Yearly,
            'start_date' => now()->toDateString(),
            'renewal_date' => now()->addDays(100)->toDateString(),
            'notice_period_days' => 60,
            'auto_renew' => true,
        ], $term));
    }

    public function test_loose_services_can_be_combined_after_the_fact(): void
    {
        $hosting = $this->service('Hosting');
        $domain = $this->service('Domein');

        $group = $this->merge([$hosting, $domain]);

        $this->assertTrue($group->isGroup());
        $this->assertSame('Website hosting & domains', $group->name);
        $this->assertSame(2, $group->services()->count());
        $this->assertTrue($hosting->fresh()->isInGroup());
    }

    /** The whole point: one term, not three. */
    public function test_the_collection_term_overrides_the_services(): void
    {
        $hosting = $this->service('Hosting', [
            'renewal_date' => now()->addDays(5)->toDateString(),
            'notice_period_days' => 7,
        ]);

        $group = $this->merge([$hosting], ['renewal_date' => now()->addDays(100)->toDateString()]);

        $hosting->refresh();
        $this->assertSame($group->renewal_date->toDateString(), $hosting->renewal_date->toDateString());
        $this->assertSame(60, $hosting->notice_period_days);
    }

    public function test_changing_the_collection_term_reaches_its_services(): void
    {
        $hosting = $this->service('Hosting');
        $group = $this->merge([$hosting]);

        $group->forceFill(['renewal_date' => now()->addDays(200)->toDateString()])->save();
        $group->syncTermToServices();

        $this->assertSame(
            $group->renewal_date->toDateString(),
            $hosting->fresh()->renewal_date->toDateString(),
        );
    }

    /** Services keep their own prices; the collection is the total. */
    public function test_the_collection_totals_its_services(): void
    {
        $group = $this->merge([
            $this->service('Hosting', ['sale_price' => 25, 'cost_price' => 10]),
            $this->service('Domein', ['sale_price' => 15, 'cost_price' => 6, 'quantity' => 2]),
        ]);

        $this->assertEqualsWithDelta(55.0, $group->fresh()->periodSalePrice(), 0.01);
        $this->assertEqualsWithDelta(22.0, $group->fresh()->periodCostPrice(), 0.01);
        // It has no price of its own to disagree with that.
        $this->assertNull($group->sale_price);
    }

    /** Selecting an existing collection adds to it rather than nesting. */
    public function test_a_service_can_be_added_to_an_existing_collection(): void
    {
        $group = $this->merge([$this->service('Hosting')]);
        $extra = $this->service('Certificaat');

        $again = ContractGroups::merge(new Collection([$group, $extra]), ['name' => $group->name]);

        $this->assertTrue($again->is($group));
        $this->assertSame(2, $group->services()->count());
        $this->assertFalse($extra->fresh()->isGroup());
    }

    public function test_two_collections_cannot_be_merged_into_each_other(): void
    {
        $a = $this->merge([$this->service('Hosting')], ['name' => 'A']);
        $b = $this->merge([$this->service('Mail')], ['name' => 'B']);

        $this->expectException(RuntimeException::class);
        ContractGroups::merge(new Collection([$a, $b]), ['name' => 'C']);
    }

    public function test_services_of_different_customers_cannot_be_combined(): void
    {
        $other = Company::create(['name' => 'Andere BV', 'country' => 'NL']);
        $mine = $this->service('Hosting');
        $theirs = Contract::create([
            'company_id' => $other->id, 'name' => 'Hun hosting', 'type' => 'license',
            'quantity' => 1, 'cost_price' => 1, 'sale_price' => 2, 'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly, 'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
        ]);

        $this->expectException(RuntimeException::class);
        ContractGroups::merge(new Collection([$mine, $theirs]), ['name' => 'Mix']);
    }

    /** Cancelling the whole thing ends everything in it. */
    public function test_cancelling_a_collection_cancels_its_services(): void
    {
        $hosting = $this->service('Hosting');
        $domain = $this->service('Domein');
        $group = $this->merge([$hosting, $domain]);

        $group->cancelNow();

        $this->assertSame(ContractStatus::Cancelled, $hosting->fresh()->status);
        $this->assertSame(ContractStatus::Cancelled, $domain->fresh()->status);
    }

    /** ...but one part can go on its own. */
    public function test_a_single_service_can_be_cancelled_inside_a_collection(): void
    {
        $hosting = $this->service('Hosting');
        $domain = $this->service('Domein');
        $group = $this->merge([$hosting, $domain]);

        $domain->cancelNow();

        $this->assertSame(ContractStatus::Cancelled, $domain->fresh()->status);
        $this->assertSame(ContractStatus::Active, $hosting->fresh()->status);
        $this->assertSame(ContractStatus::Active, $group->fresh()->status);
    }

    /** Taking one out leaves it running on the dates it already had. */
    public function test_a_service_taken_out_keeps_its_dates_and_runs_alone(): void
    {
        $hosting = $this->service('Hosting');
        $group = $this->merge([$hosting]);
        $inherited = $hosting->fresh()->renewal_date->toDateString();

        $hosting->fresh()->detachFromGroup();

        $loose = $hosting->fresh();
        $this->assertFalse($loose->isInGroup());
        $this->assertSame($inherited, $loose->renewal_date->toDateString());
        $this->assertSame(ContractStatus::Active, $loose->status);
    }

    public function test_dissolving_a_collection_leaves_its_services_standing(): void
    {
        $hosting = $this->service('Hosting');
        $group = $this->merge([$hosting]);

        $this->assertSame(1, ContractGroups::dissolve($group));

        $this->assertNull(Contract::find($group->id));
        $this->assertFalse($hosting->fresh()->isInGroup());
    }

    /**
     * The collection's dates are copied onto its services, so a blank one
     * would erase the dates they were running on — and the warnings with
     * them. It takes the soonest instead.
     */
    public function test_a_blank_renewal_date_falls_back_to_the_soonest_service(): void
    {
        $soon = $this->service('Hosting', ['renewal_date' => now()->addDays(20)->toDateString()]);
        $later = $this->service('Domein', ['renewal_date' => now()->addDays(300)->toDateString()]);

        $group = ContractGroups::merge(new Collection([$soon, $later]), [
            'name' => 'Website hosting & domeinen',
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => null,
            'start_date' => null,
        ]);

        $this->assertSame(
            now()->addDays(20)->toDateString(),
            $group->renewal_date->toDateString(),
        );
        $this->assertNotNull($soon->fresh()->renewal_date);
        $this->assertNotNull($later->fresh()->renewal_date);
    }

    /** The notice that has to be given earliest governs the whole thing. */
    public function test_a_blank_notice_period_takes_the_longest_of_the_services(): void
    {
        $group = ContractGroups::merge(new Collection([
            $this->service('Hosting', ['notice_period_days' => 30]),
            $this->service('Domein', ['notice_period_days' => 90]),
        ]), ['name' => 'Bundel', 'billing_cycle' => BillingCycle::Yearly]);

        $this->assertSame(90, $group->notice_period_days);
    }

    /** Derived money follows the services, so margin is not reported as nil. */
    public function test_margin_on_a_collection_comes_from_its_services(): void
    {
        $group = $this->merge([
            $this->service('Hosting', ['cost_price' => 12.5, 'sale_price' => 25]),
            $this->service('Domein', ['cost_price' => 8, 'sale_price' => 15]),
        ]);

        $group = $group->fresh(['services']);
        $this->assertEqualsWithDelta(40.0, (float) $group->total_sale, 0.01);
        $this->assertEqualsWithDelta(20.5, (float) $group->effective_cost, 0.01);
        $this->assertEqualsWithDelta(19.5, (float) $group->margin_eur, 0.01);
        $this->assertEqualsWithDelta(48.8, (float) $group->margin_pct, 0.1);
    }

    /**
     * The reason the renewal queries changed: a service inside a collection
     * renews when its collection does, so warning about both is warning
     * twice about one thing.
     */
    public function test_renewal_lists_show_the_collection_and_not_its_services(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $group = $this->merge([
            $this->service('Hosting'),
            $this->service('Domein'),
        ], ['renewal_date' => now()->addDays(20)->toDateString()]);

        $ids = Contract::query()->withUpcomingRenewalTerm()->pluck('id');

        $this->assertTrue($ids->contains($group->id));
        $this->assertSame(1, $ids->count());
        $this->assertSame(1, UpcomingRenewals::attentionCount());
    }

    public function test_the_contract_list_shows_collections_not_their_services(): void
    {
        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        $hosting = $this->service('Hosting');
        $loose = $this->service('Losse dienst');
        $group = $this->merge([$hosting]);

        Livewire::test(ListContracts::class)
            ->assertCanSeeTableRecords(Contract::query()->whereIn('id', [$group->id, $loose->id])->get())
            ->assertCanNotSeeTableRecords(Contract::query()->whereKey($hosting->id)->get());
    }

    /** One contract is one contract, however many services are inside it. */
    public function test_the_dashboard_counts_a_collection_once(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->merge([$this->service('Hosting'), $this->service('Domein')]);

        Livewire::test(PortfolioStats::class)
            ->assertSee(__('Active contracts'))
            // Two services and their collection: one contract, and the
            // revenue of the two services, counted once.
            ->assertSee('1');
    }

    /**
     * A collection totals its services, so counting it alongside them
     * doubled the customer's MRR — which is the number an operator quotes.
     */
    public function test_a_customer_s_revenue_counts_a_collection_once(): void
    {
        $this->merge([
            $this->service('Hosting', ['sale_price' => 25, 'cost_price' => 10]),
            $this->service('Domein', ['sale_price' => 15, 'cost_price' => 5]),
        ]);

        $company = $this->company->fresh();

        // 40 a year over twelve months, counted once.
        $this->assertEqualsWithDelta(40 / 12, $company->mrr(), 0.01);
        $this->assertEqualsWithDelta(25.0, $company->annualMargin(), 0.01);
        $this->assertSame(1, $company->activeContractCount());
    }

    /** The same on the customer page: one row, not the row and its parts. */
    public function test_the_customer_page_lists_the_collection_and_not_its_services(): void
    {
        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        $hosting = $this->service('Hosting');
        $loose = $this->service('Losse dienst');
        $group = $this->merge([$hosting]);

        Livewire::test(ContractsRelationManager::class, [
            'ownerRecord' => $this->company->fresh(),
            'pageClass' => ViewCompany::class,
        ])
            ->assertCanSeeTableRecords(Contract::query()->whereIn('id', [$group->id, $loose->id])->get())
            ->assertCanNotSeeTableRecords(Contract::query()->whereKey($hosting->id)->get());
    }

    public function test_the_portal_lists_the_collection_and_opens_its_services(): void
    {
        $group = $this->merge([
            $this->service('Hosting', ['sale_price' => 25]),
            $this->service('Domein', ['sale_price' => 15]),
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Anna Bakker',
            'email' => 'anna@klant.example',
        ]);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Website hosting &amp; domains', false)
            // The parts are not loose lines on the dashboard.
            ->assertDontSee('>Hosting<', false);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.contracts.show', $group))
            ->assertOk()
            ->assertSee('Hosting')
            ->assertSee('Domein');
    }

    /** A customer must not be able to open somebody else's contract. */
    public function test_the_portal_page_is_scoped_to_the_contact_s_company(): void
    {
        $other = Company::create(['name' => 'Andere BV', 'country' => 'NL']);
        $theirGroup = Contract::create([
            'company_id' => $other->id, 'name' => 'Hun contract', 'is_group' => true,
            'type' => 'service', 'quantity' => 1, 'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Yearly, 'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Anna Bakker',
            'email' => 'anna@klant.example',
        ]);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.contracts.show', $theirGroup))
            ->assertNotFound();
    }

    /** A single service has no inside; there is nothing to open. */
    public function test_a_plain_service_has_no_portal_page_of_its_own(): void
    {
        $service = $this->service('Losse dienst');
        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Anna Bakker',
            'email' => 'anna@klant.example',
        ]);

        $this->actingAs($contact, 'portal')
            ->get(route('portal.contracts.show', $service))
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\PlannedTaskKind;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\ContractCancelled;
use App\Notifications\DirectDebitStopped;
use App\Support\ContractCancellation;
use App\Support\ContractGroups;
use App\Support\ContractMoves;
use App\Support\ContractTermination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * What a customer may end themselves, and what has to happen when they do.
 * A cancelled row is the easy part: the licence, hosting or domain behind
 * it keeps renewing at the supplier until a person stops it.
 */
class PortalCancellationTest extends TestCase
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
            'billing_cycle' => BillingCycle::Monthly,
            'start_date' => '2026-10-01',
            'next_invoice_date' => '2026-10-01',
            'notice_period_days' => 30,
            'status' => ContractStatus::Active,
        ], $overrides));
    }

    private function contact(): Contact
    {
        return Contact::query()->firstOrCreate(
            ['email' => 'anna@klant.example'],
            ['company_id' => $this->company->id, 'name' => 'Anna Bakker'],
        );
    }

    /** @param list<Contract> $services */
    private function group(array $services, array $term = []): Contract
    {
        return ContractGroups::merge(new BaseCollection($services), array_merge([
            'name' => 'Website hosting & domeinen',
            'billing_cycle' => BillingCycle::Monthly,
            'start_date' => '2026-10-01',
            'renewal_date' => '2026-11-01',
            'notice_period_days' => 0,
        ], $term));
    }

    private function cancelAs(Contact $contact, Contract $contract, ?string $endsOn = null)
    {
        $ends = $endsOn ?? ContractCancellation::for($contract)->endsOn()?->toDateString();

        return $this->actingAs($contact, 'portal')
            ->post(route('portal.contracts.cancel', $contract), ['confirmed_ends_on' => $ends]);
    }

    public function test_a_customer_can_cancel_a_loose_service(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();
        User::factory()->admin()->create();

        $service = $this->service('Hosting');

        $this->cancelAs($this->contact(), $service)->assertRedirect(route('portal.dashboard'));

        $this->assertSame(ContractStatus::Cancelled, $service->fresh()->status);
    }

    /** A whole contract, and the services in it go with it. */
    public function test_a_customer_can_cancel_a_whole_collection(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();
        User::factory()->admin()->create();

        $hosting = $this->service('Hosting');
        $domain = $this->service('Domein');
        $group = $this->group([$hosting, $domain]);

        $this->cancelAs($this->contact(), $group)->assertRedirect(route('portal.dashboard'));

        $this->assertSame(ContractStatus::Cancelled, $group->fresh()->status);
        $this->assertSame(ContractStatus::Cancelled, $hosting->fresh()->status);
        $this->assertSame(ContractStatus::Cancelled, $domain->fresh()->status);
    }

    /** ...or just one part of it. */
    public function test_a_customer_can_cancel_one_service_inside_a_collection(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();
        User::factory()->admin()->create();

        $hosting = $this->service('Hosting');
        $domain = $this->service('Domein');
        $group = $this->group([$hosting, $domain]);

        $this->cancelAs($this->contact(), $domain->fresh())->assertRedirect(route('portal.dashboard'));

        $this->assertSame(ContractStatus::Cancelled, $domain->fresh()->status);
        $this->assertSame(ContractStatus::Active, $hosting->fresh()->status);
        $this->assertSame(ContractStatus::Active, $group->fresh()->status);
    }

    /**
     * Inside the notice window on a yearly term the renewal is already
     * committed, so this stops being a button and becomes a conversation.
     */
    public function test_a_yearly_contract_inside_its_notice_window_cannot_be_cancelled_in_the_portal(): void
    {
        Carbon::setTestNow('2026-11-20');
        Notification::fake();

        $service = $this->service('Licentie', [
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => '2026-12-01',
            'notice_period_days' => 30,
        ]);

        $this->cancelAs($this->contact(), $service, '2026-12-01')
            ->assertSessionHasErrors('contract');

        $this->assertSame(ContractStatus::Active, $service->fresh()->status);
    }

    /** With a month still to go, the same contract is theirs to end. */
    public function test_the_same_contract_can_be_cancelled_before_the_notice_window(): void
    {
        Carbon::setTestNow('2026-10-20');
        Notification::fake();
        User::factory()->admin()->create();

        $service = $this->service('Licentie', [
            'billing_cycle' => BillingCycle::Yearly,
            'renewal_date' => '2026-12-01',
            'notice_period_days' => 30,
        ]);

        $this->cancelAs($this->contact(), $service)->assertRedirect(route('portal.dashboard'));

        $this->assertSame(ContractStatus::Cancelled, $service->fresh()->status);
    }

    /** A page left open overnight must not cancel on dates nobody agreed. */
    public function test_a_stale_confirmation_is_refused(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();

        $service = $this->service('Hosting');

        $this->cancelAs($this->contact(), $service, '2020-01-01')
            ->assertSessionHasErrors('contract');

        $this->assertSame(ContractStatus::Active, $service->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_another_company_s_contract(): void
    {
        $other = Company::create(['name' => 'Andere BV', 'country' => 'NL']);
        $theirs = Contract::create([
            'company_id' => $other->id, 'name' => 'Hun hosting', 'type' => 'license',
            'quantity' => 1, 'cost_price' => 1, 'sale_price' => 2, 'currency' => 'EUR',
            'billing_cycle' => BillingCycle::Monthly, 'start_date' => now()->toDateString(),
            'status' => ContractStatus::Active,
        ]);

        $this->cancelAs($this->contact(), $theirs, '2030-01-01')->assertNotFound();

        $this->assertSame(ContractStatus::Active, $theirs->fresh()->status);
    }

    /**
     * The point of the whole exercise: a cancelled row does not stop the
     * supplier billing us, so a person has to be told to go and do it.
     */
    public function test_cancelling_raises_a_task_and_tells_the_staff(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();
        $viewer = User::factory()->viewer()->create();

        $service = $this->service('Hosting');
        ContractTermination::cancel($service, by: 'customer');

        $task = PlannedTask::query()->firstOrFail();
        $this->assertSame(PlannedTaskKind::Cancellation, $task->kind);
        $this->assertSame($this->company->id, $task->company_id);
        $this->assertStringContainsString('Hosting', $task->title);
        // Due when the service actually stops, so it cannot be left late.
        $this->assertSame('2026-11-30', $task->due_on?->toDateString());

        Notification::assertSentTo([$admin, $manager], ContractCancelled::class);
        Notification::assertNotSentTo($viewer, ContractCancelled::class);
    }

    /** The task names the parts, because each is something to go and stop. */
    public function test_the_task_for_a_collection_names_its_services(): void
    {
        Carbon::setTestNow('2026-10-15');
        Notification::fake();
        User::factory()->admin()->create();

        $group = $this->group([$this->service('Hosting'), $this->service('Domein')]);
        ContractTermination::cancel($group, by: 'customer');

        $task = PlannedTask::query()->firstOrFail();
        $this->assertStringContainsString('Hosting', (string) $task->notes);
        $this->assertStringContainsString('Domein', (string) $task->notes);
    }

    /**
     * A mandate is given for one agreement. Moving the service it covers
     * means the thing being billed is no longer the thing authorised.
     */
    public function test_moving_a_service_with_a_direct_debit_stops_it_and_emails_the_customer(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $hosting = $this->service('Hosting', [
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
        ]);
        $group = $this->group([$this->service('Domein')]);

        $this->assertTrue(ContractMoves::stopsDirectDebit($hosting));

        ContractMoves::into($hosting, $group);

        $hosting->refresh();
        $this->assertFalse((bool) $hosting->auto_collect);
        $this->assertTrue($hosting->isInGroup());
        Notification::assertSentTo($contact, DirectDebitStopped::class);
    }

    /** And the same on the way out. */
    public function test_taking_a_service_out_with_a_direct_debit_stops_it_too(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $hosting = $this->service('Hosting');
        $this->group([$hosting]);
        $hosting->refresh()->forceFill(['auto_collect' => true, 'stripe_payment_status' => 'active'])->save();

        ContractMoves::out($hosting->fresh());

        $this->assertFalse((bool) $hosting->fresh()->auto_collect);
        $this->assertFalse($hosting->fresh()->isInGroup());
        Notification::assertSentTo($contact, DirectDebitStopped::class);
    }

    /** No mandate, no mail: the move is unremarkable. */
    public function test_moving_a_service_without_a_direct_debit_says_nothing(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting');
        $group = $this->group([$this->service('Domein')]);

        ContractMoves::into($hosting, $group);

        $this->assertSame([], ContractMoves::warnings([$hosting->fresh()]));
        Notification::assertNothingSent();
    }

    /** The operator is warned before they confirm, not after. */
    public function test_the_operator_is_warned_before_a_move_strips_a_mandate(): void
    {
        $hosting = $this->service('Hosting', ['auto_collect' => true]);

        $warnings = ContractMoves::warnings([$hosting]);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Hosting', $warnings[0]);
    }

    /** Two mandates for one agreement would debit the customer twice. */
    public function test_a_service_inside_a_collection_cannot_take_its_own_direct_debit(): void
    {
        $hosting = $this->service('Hosting');
        $this->group([$hosting]);

        $this->actingAs($this->contact(), 'portal')
            ->get(route('portal.auto-collect', $hosting->fresh()))
            ->assertRedirect(route('portal.contracts.show', $hosting->fresh()->parent))
            ->assertSessionHasErrors('stripe');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

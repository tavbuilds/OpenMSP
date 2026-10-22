<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\PlannedTaskKind;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractBillingChange;
use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\BillingAmountChanged;
use App\Notifications\ContractCancelled;
use App\Services\StripeBillingService;
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

    /**
     * A Stripe that records what it was asked to collect.
     *
     * @param  list<array{int, float}>  $repriced
     */
    private function stripe(array &$repriced): StripeBillingService
    {
        return new class($repriced) extends StripeBillingService
        {
            public function __construct(public array &$seen) {}

            public function updateSubscriptionAmount(Contract $contract): void
            {
                $this->seen[] = [$contract->id, (float) $contract->total_sale];
            }

            public function cancelSubscription(Contract $contract): void {}
        };
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
     * A move is made, not proposed.
     *
     * An operator changing a contract is carrying out what the customer
     * asked for, so there is nothing to approve. The mandate survives the
     * new amount — it was given to us, not to a sum — and the customer is
     * told what will leave their account from now on.
     */
    public function test_adding_a_service_re_prices_the_subscription_and_tells_the_customer(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $repriced = [];
        $this->swap(StripeBillingService::class, $this->stripe($repriced));

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $this->assertTrue(ContractMoves::into($hosting, $group->fresh()));

        $this->assertSame($group->id, $hosting->fresh()->parent_id);
        $this->assertSame([[$group->id, 40.0]], $repriced);
        $this->assertTrue($group->fresh()->collectsByDirectDebit());

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertEqualsWithDelta(15.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $change->new_amount, 0.01);
        $this->assertSame('Hosting', $change->service_name);
        $this->assertNotNull($change->fresh()->notified_at);
        Notification::assertSentTo($contact, BillingAmountChanged::class);
    }

    public function test_removing_a_service_re_prices_the_subscription_and_tells_the_customer(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $group = $this->group([$hosting, $this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $repriced = [];
        $this->swap(StripeBillingService::class, $this->stripe($repriced));

        ContractMoves::out($hosting->fresh());

        $this->assertNull($hosting->fresh()->parent_id);
        $this->assertSame([[$group->id, 15.0]], $repriced);

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertEqualsWithDelta(40.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $change->new_amount, 0.01);
        $this->assertFalse($change->goesUp());
        Notification::assertSentTo($contact, BillingAmountChanged::class);
    }

    /**
     * The gap this closes: we invoiced one amount and debited another.
     *
     * Editing a quantity in the panel changed what the contract is worth
     * and left the subscription on last month's figure.
     */
    public function test_editing_a_quantity_inside_a_collection_re_prices_the_subscription(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25, 'quantity' => 1]);
        $group = $this->group([$hosting, $this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $repriced = [];
        $this->swap(StripeBillingService::class, $this->stripe($repriced));

        $hosting->fresh()->update(['quantity' => 3]);

        // 3 × 25 + 15.
        $this->assertSame([[$group->id, 90.0]], $repriced);

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertSame(ContractBillingChange::REASON_AMOUNT_EDITED, $change->reason);
        $this->assertEqualsWithDelta(40.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(90.0, (float) $change->new_amount, 0.01);
        $this->assertSame('Hosting', $change->service_name);
        Notification::assertSentTo($contact, BillingAmountChanged::class);
    }

    public function test_editing_the_price_of_a_loose_collecting_service_re_prices_it(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $hosting->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $repriced = [];
        $this->swap(StripeBillingService::class, $this->stripe($repriced));

        $hosting->fresh()->update(['sale_price' => 30]);

        $this->assertSame([[$hosting->id, 30.0]], $repriced);
        $this->assertEqualsWithDelta(25.0, (float) ContractBillingChange::query()->firstOrFail()->previous_amount, 0.01);
    }

    /** Nothing to collect differently, nothing to say. */
    public function test_editing_a_price_without_a_mandate_tells_nobody(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);

        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function updateSubscriptionAmount(Contract $contract): void
            {
                throw new \RuntimeException('There is no subscription to re-price');
            }
        });

        $hosting->update(['sale_price' => 30]);

        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** A rename or a new renewal date costs the customer nothing. */
    public function test_an_edit_that_does_not_touch_the_money_says_nothing(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $hosting->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $hosting->fresh()->update(['name' => 'Hosting (renamed)', 'notes' => 'something']);

        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** The only service leaving takes the mandate with it, unchanged. */
    public function test_the_last_service_out_carries_the_subscription_without_a_word(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $group = $this->group([$hosting]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $moved = [];
        $this->swap(StripeBillingService::class, new class($moved) extends StripeBillingService
        {
            public function __construct(public array &$moved) {}

            public function transferSubscription(Contract $from, Contract $to): void
            {
                $this->moved[] = [$from->id, $to->id];
                $to->forceFill([
                    'auto_collect' => true,
                    'stripe_payment_status' => 'active',
                    'stripe_subscription_id' => $from->stripe_subscription_id,
                ])->save();
                $from->forceFill(['auto_collect' => false, 'stripe_subscription_id' => null])->save();
            }
        });

        ContractMoves::out($hosting->fresh());

        $this->assertNull($hosting->fresh()->parent_id);
        $this->assertSame([[$group->id, $hosting->id]], $moved);

        // They pay exactly what they paid yesterday.
        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** The mandate follows the service in, and covers more than it did. */
    public function test_a_collecting_service_joining_a_collection_hands_its_mandate_over(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $hosting->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);

        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function updateSubscriptionAmount(Contract $contract): void {}

            public function transferSubscription(Contract $from, Contract $to): void
            {
                $to->forceFill([
                    'auto_collect' => true,
                    'stripe_payment_status' => 'active',
                    'stripe_subscription_id' => $from->stripe_subscription_id,
                ])->save();
            }
        });

        ContractMoves::into($hosting->fresh(), $group->fresh());

        // Stripe took 25 for the service alone; it now covers 40.
        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertEqualsWithDelta(25.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $change->new_amount, 0.01);
        $this->assertSame($group->id, $hosting->fresh()->parent_id);
        Notification::assertSentTo($contact, BillingAmountChanged::class);
    }

    /**
     * Two mandates becoming one, for the same money.
     *
     * The customer is already paying both halves; afterwards they pay the
     * sum of them on one debit. Nothing changed for them, so nothing is
     * sent — a mail about an amount that did not move is noise.
     */
    public function test_two_mandates_for_the_same_total_merge_without_a_word(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $hosting->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_service',
        ])->save();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_group',
        ])->save();

        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function updateSubscriptionAmount(Contract $contract): void {}

            public function cancelSubscription(Contract $contract): void {}
        });

        ContractMoves::into($hosting->fresh(), $group->fresh());

        $this->assertSame($group->id, $hosting->fresh()->parent_id);
        $this->assertFalse($hosting->fresh()->collectsByDirectDebit());
        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** No mandate anywhere, no one to tell. */
    public function test_moving_a_service_without_a_direct_debit_says_nothing(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting');
        $group = $this->group([$this->service('Domein')]);

        $this->assertTrue(ContractMoves::into($hosting, $group));

        $this->assertSame($group->id, $hosting->fresh()->parent_id);
        $this->assertSame([], ContractMoves::warnings([$hosting->fresh()]));
        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** The operator is told what the customer will be told. */
    public function test_the_operator_is_warned_before_a_move_changes_a_collection(): void
    {
        $hosting = $this->service('Hosting', [
            'auto_collect' => true,
            'stripe_subscription_id' => 'sub_test',
        ]);

        $warnings = ContractMoves::warnings([$hosting]);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Hosting', $warnings[0]);
    }

    /** A customer with no email address cannot be told, and we record that. */
    public function test_a_company_without_contacts_still_records_the_change(): void
    {
        Notification::fake();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        $repriced = [];
        $this->swap(StripeBillingService::class, $this->stripe($repriced));

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertNull($change->notified_at);
        $this->assertSame([[$group->id, 40.0]], $repriced);
        Notification::assertNothingSent();
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

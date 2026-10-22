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
use App\Notifications\BillingChangePending;
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
     * The mandate survives a move — it was given to us, not to an amount —
     * so the customer is asked to approve the new sum rather than to set
     * the whole thing up again.
     */
    public function test_adding_a_service_to_a_collecting_contract_asks_the_customer_for_the_new_amount(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertSame('pending', $change->status);
        $this->assertEqualsWithDelta(15.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $change->new_amount, 0.01);
        $this->assertSame('Hosting', $change->service_name);

        // Nothing has been collected differently yet.
        $this->assertTrue($group->fresh()->collectsByDirectDebit());
        Notification::assertSentTo($contact, BillingChangePending::class);
    }

    public function test_removing_a_service_asks_the_customer_about_the_lower_amount(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $group = $this->group([$hosting, $this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::out($hosting->fresh());

        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertEqualsWithDelta(40.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $change->new_amount, 0.01);
        $this->assertFalse($change->goesUp());
    }

    /** A second move supersedes the question rather than stacking on it. */
    public function test_a_second_change_replaces_the_one_still_unanswered(): void
    {
        Notification::fake();
        $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        ContractMoves::into($this->service('SSL', ['sale_price' => 10]), $group->fresh());

        $this->assertSame(1, ContractBillingChange::query()->pending()->count());
        $pending = ContractBillingChange::query()->pending()->firstOrFail();
        // Still measured against what Stripe is actually collecting.
        $this->assertEqualsWithDelta(15.0, (float) $pending->previous_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $pending->new_amount, 0.01);
    }

    /** Put straight back: the drop they were asked about never happens. */
    public function test_undoing_a_move_retires_the_question_instead_of_leaving_it_up(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting', ['sale_price' => 25]);
        $group = $this->group([$hosting, $this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::out($hosting->fresh());
        $this->assertSame(1, ContractBillingChange::query()->pending()->count());

        ContractMoves::into($hosting->fresh(), $group->fresh());

        // Back where it started, so there is nothing left to approve —
        // and nothing still on screen promising a lower amount.
        $this->assertSame(0, ContractBillingChange::query()->pending()->count());
        $this->assertSame('superseded', ContractBillingChange::query()->latest('id')->firstOrFail()->status);
        $this->assertEqualsWithDelta(40.0, (float) $group->fresh()->total_sale, 0.01);
    }

    /** The only service leaving takes the mandate with it, unchanged. */
    public function test_the_last_service_out_carries_the_subscription_without_asking_anything(): void
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
                $from->forceFill([
                    'auto_collect' => false,
                    'stripe_subscription_id' => null,
                ])->save();
            }
        });

        ContractMoves::out($hosting->fresh());

        $this->assertSame([[$group->id, $hosting->id]], $moved);

        // The customer pays exactly what they paid yesterday, so there is
        // nothing for them to approve.
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

        // Stripe was taking 25 for the service alone; the collection it
        // now covers is worth 40, and that is the difference to approve.
        $change = ContractBillingChange::query()->firstOrFail();
        $this->assertEqualsWithDelta(25.0, (float) $change->previous_amount, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $change->new_amount, 0.01);
        $this->assertSame($group->id, $change->contract_id);
        Notification::assertSentTo($contact, BillingChangePending::class);
    }

    /** No mandate anywhere, no question to ask. */
    public function test_moving_a_service_without_a_direct_debit_says_nothing(): void
    {
        Notification::fake();
        $this->contact();

        $hosting = $this->service('Hosting');
        $group = $this->group([$this->service('Domein')]);

        ContractMoves::into($hosting, $group);

        $this->assertSame([], ContractMoves::warnings([$hosting->fresh()]));
        $this->assertSame(0, ContractBillingChange::query()->count());
        Notification::assertNothingSent();
    }

    /** The operator is told what the move will ask of the customer. */
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

    /** Approving re-prices the subscription; declining stops it. */
    public function test_the_customer_can_decline_and_the_collection_stops(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        $change = ContractBillingChange::query()->firstOrFail();

        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function cancelSubscription(Contract $contract): void
            {
                $contract->forceFill([
                    'auto_collect' => false,
                    'stripe_payment_status' => 'cancelled',
                ])->save();
            }
        });

        $this->actingAs($contact, 'portal')
            ->post(route('portal.billing-changes.decline', $change))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('declined', $change->fresh()->status);
        $this->assertFalse($group->fresh()->collectsByDirectDebit());
    }

    /** A cancel that did not land must not be reported as one that did. */
    public function test_a_failed_stop_leaves_the_direct_debit_and_the_change_alone(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        $change = ContractBillingChange::query()->firstOrFail();

        // The real service logs its failures and returns quietly, so the
        // mandate is still standing when it comes back.
        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function cancelSubscription(Contract $contract): void {}
        });

        $this->actingAs($contact, 'portal')
            ->post(route('portal.billing-changes.decline', $change))
            ->assertSessionHasErrors('billing');

        $this->assertSame('pending', $change->fresh()->status);
        $this->assertTrue($group->fresh()->collectsByDirectDebit());
    }

    public function test_one_customer_cannot_answer_another_s_billing_change(): void
    {
        $other = Company::create(['name' => 'Andere BV', 'country' => 'NL']);
        $change = ContractBillingChange::query()->create([
            'contract_id' => $this->service('Hosting')->id,
            'company_id' => $other->id,
            'previous_amount' => 10,
            'new_amount' => 20,
            'currency' => 'EUR',
            'reason' => ContractBillingChange::REASON_SERVICE_ADDED,
            'status' => 'pending',
        ]);

        $this->actingAs($this->contact(), 'portal')
            ->post(route('portal.billing-changes.confirm', $change))
            ->assertNotFound();

        $this->assertSame('pending', $change->fresh()->status);
    }

    /** It is in front of them wherever they land, because we are still
     *  collecting the old amount until they answer. */
    public function test_a_pending_change_is_shown_in_the_portal(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());

        $this->actingAs($contact, 'portal')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee(__('Approve a change to your direct debit'));
    }

    /** Approving re-prices the subscription we already have a mandate for. */
    public function test_approving_re_prices_the_subscription_rather_than_asking_again(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        $change = ContractBillingChange::query()->firstOrFail();

        $repriced = [];
        $this->swap(StripeBillingService::class, new class($repriced) extends StripeBillingService
        {
            public function __construct(public array &$seen) {}

            public function updateSubscriptionAmount(Contract $contract): void
            {
                $this->seen[] = [$contract->id, (float) $contract->total_sale];
            }
        });

        $this->actingAs($contact, 'portal')
            ->post(route('portal.billing-changes.confirm', $change))
            ->assertRedirect();

        $this->assertSame('confirmed', $change->fresh()->status);
        $this->assertNotNull($change->fresh()->confirmed_at);
        $this->assertSame([[$group->id, 40.0]], $repriced);

        // The mandate is untouched: nothing was cancelled on the way.
        $this->assertTrue($group->fresh()->collectsByDirectDebit());
    }

    /** If Stripe will not take the new amount, nothing is marked agreed. */
    public function test_a_failed_re_price_leaves_the_change_waiting(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        $change = ContractBillingChange::query()->firstOrFail();

        $this->swap(StripeBillingService::class, new class extends StripeBillingService
        {
            public function __construct() {}

            public function updateSubscriptionAmount(Contract $contract): void
            {
                throw new \RuntimeException('Stripe is down');
            }
        });

        $this->actingAs($contact, 'portal')
            ->post(route('portal.billing-changes.confirm', $change))
            ->assertSessionHasErrors('billing');

        $this->assertSame('pending', $change->fresh()->status);
    }

    /** An answered change is not answered twice. */
    public function test_a_change_already_answered_does_nothing_the_second_time(): void
    {
        Notification::fake();
        $contact = $this->contact();

        $group = $this->group([$this->service('Domein', ['sale_price' => 15])]);
        $group->forceFill([
            'auto_collect' => true,
            'stripe_payment_status' => 'active',
            'stripe_subscription_id' => 'sub_test',
        ])->save();

        ContractMoves::into($this->service('Hosting', ['sale_price' => 25]), $group->fresh());
        $change = ContractBillingChange::query()->firstOrFail();
        $change->forceFill(['status' => 'declined', 'declined_at' => now()])->save();

        $calls = 0;
        $this->swap(StripeBillingService::class, new class($calls) extends StripeBillingService
        {
            public function __construct(public int &$calls) {}

            public function updateSubscriptionAmount(Contract $contract): void
            {
                $this->calls++;
            }
        });

        $this->actingAs($contact, 'portal')
            ->post(route('portal.billing-changes.confirm', $change))
            ->assertRedirect();

        $this->assertSame('declined', $change->fresh()->status);
        $this->assertSame(0, $calls);
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

<?php

namespace App\Support;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\MoveOutcome;
use App\Enums\ProductType;
use App\Models\Contract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turning loose services into a collection, and back again.
 *
 * Most installs already hold a row per service — hosting here, the domain
 * there, an SSL certificate somewhere else — all of them renewing on their
 * own dates. Grouping them has to work on that data as it stands, not only
 * on contracts created from today.
 */
final class ContractGroups
{
    /**
     * Gathers the given contracts into one collection.
     *
     * If exactly one collection is among them it absorbs the rest, which is
     * how you add a service to a group you already made. Otherwise a new one
     * is created with the term supplied by the operator.
     *
     * A service whose direct debit the move would change does not join:
     * that is the customer's to approve, and $deferred comes back saying
     * which ones are waiting on them so the operator is not left thinking
     * the collection holds more than it does.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  array<string, mixed>  $term  name, billing_cycle, start_date, renewal_date, notice_period_days, auto_renew
     * @param  array{awaiting: list<string>, blocked: list<string>}|null  $deferred  filled in on return
     */
    public static function merge(Collection $contracts, array $term, ?array &$deferred = null): Contract
    {
        $deferred = ['awaiting' => [], 'blocked' => []];

        $contracts = $contracts->filter(fn (Contract $c) => ! $c->isInGroup() || $c->isGroup());

        if ($contracts->isEmpty()) {
            throw new RuntimeException(__('Select at least one contract to group.'));
        }

        $companies = $contracts->pluck('company_id')->unique();
        if ($companies->count() > 1) {
            throw new RuntimeException(__('All contracts in a collection must belong to the same customer.'));
        }

        $groups = $contracts->filter(fn (Contract $c) => $c->isGroup());
        if ($groups->count() > 1) {
            throw new RuntimeException(__('Pick at most one existing collection — two cannot be merged into each other.'));
        }

        $term = self::fillInTerm($term, $contracts);

        return DB::transaction(function () use ($contracts, $groups, $term, $companies, &$deferred): Contract {
            $group = $groups->first() ?? self::create((int) $companies->first(), $term, $contracts);

            if ($groups->isNotEmpty()) {
                // Adding to a collection that exists: its own term stays,
                // because its other services are already running on it.
                $group->forceFill(['name' => $term['name'] ?? $group->name])->save();
            }

            foreach ($contracts as $contract) {
                if ($contract->is($group)) {
                    continue;
                }

                // A collection swept up by mistake gives up its services
                // rather than disappearing with them inside it.
                if ($contract->isGroup()) {
                    continue;
                }

                match (ContractMoves::into($contract, $group)) {
                    MoveOutcome::AwaitingCustomer => $deferred['awaiting'][] = $contract->name,
                    MoveOutcome::Blocked => $deferred['blocked'][] = $contract->name,
                    default => null,
                };
            }

            return $group->fresh(['services']);
        });
    }

    /**
     * Fills the gaps the operator left, from the services themselves.
     *
     * The collection's dates are copied onto everything inside it, so a
     * blank renewal date would quietly erase the dates those services were
     * already running on — and with them every warning about the renewal.
     * The soonest of them is the honest answer: the agreement comes up when
     * its first part does.
     *
     * @param  array<string, mixed>  $term
     * @param  Collection<int, Contract>  $contracts
     * @return array<string, mixed>
     */
    private static function fillInTerm(array $term, Collection $contracts): array
    {
        $services = $contracts->filter(fn (Contract $c) => ! $c->isGroup());

        if (blank($term['renewal_date'] ?? null)) {
            $term['renewal_date'] = $services
                ->pluck('renewal_date')
                ->filter()
                ->sort()
                ->first();
        }

        if (blank($term['start_date'] ?? null)) {
            $term['start_date'] = $services
                ->pluck('start_date')
                ->filter()
                ->sort()
                ->first();
        }

        if (! isset($term['notice_period_days'])) {
            // The longest notice among them: the one that has to be given
            // earliest is the one that governs the whole agreement.
            $term['notice_period_days'] = (int) $services->max('notice_period_days');
        }

        return $term;
    }

    /**
     * @param  array<string, mixed>  $term
     * @param  Collection<int, Contract>  $contracts
     */
    private static function create(int $companyId, array $term, Collection $contracts): Contract
    {
        $first = $contracts->first();

        return Contract::query()->create([
            'company_id' => $companyId,
            'is_group' => true,
            'name' => $term['name'] ?? __('Collection'),
            'type' => ProductType::Service,
            'quantity' => 1,
            // The collection totals its services; a price of its own would
            // be a second, disagreeing answer to the same question.
            'cost_price' => null,
            'sale_price' => null,
            'currency' => $first?->currency ?? 'EUR',
            'billing_cycle' => $term['billing_cycle'] ?? BillingCycle::Yearly,
            'start_date' => $term['start_date'] ?? null,
            'renewal_date' => $term['renewal_date'] ?? null,
            'notice_period_days' => $term['notice_period_days'] ?? 0,
            'auto_renew' => $term['auto_renew'] ?? true,
            'status' => $term['status'] ?? ContractStatus::Active,
            'notify_renewals' => true,
        ]);
    }

    /**
     * Dissolves a collection, leaving its services standing on their own.
     *
     * The services keep the dates they were running on; the empty shell goes.
     */
    public static function dissolve(Contract $group): int
    {
        if (! $group->isGroup()) {
            return 0;
        }

        return DB::transaction(function () use ($group): int {
            $count = $group->services()->count();
            $group->services()->update(['parent_id' => null]);
            $group->delete();

            return $count;
        });
    }
}

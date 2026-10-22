<?php

namespace App\Support;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskStatus;
use App\Models\Contract;
use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\ContractCancelled;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Everything that has to happen when a contract is cancelled.
 *
 * Cancelling a row is the easy part. The licence, the hosting or the domain
 * behind it carries on renewing at the supplier until a person stops it, so
 * a cancellation raises a task and tells the staff — a mail on its own is
 * read once and then gone.
 */
final class ContractTermination
{
    /**
     * Cancels a contract and does the rest of it.
     *
     * A collection takes its services with it; the task names them, since
     * each one is something separate to go and stop.
     */
    public static function cancel(Contract $contract, string $by = 'customer'): PlannedTask
    {
        $services = $contract->isGroup()
            ? $contract->services()->get()
            : collect();

        // Stripe first: a failure there must not leave the contract live
        // while the customer has been told it is gone, and the service
        // logs rather than throws.
        self::stopCollection($contract);
        foreach ($services as $service) {
            self::stopCollection($service);
        }

        $contract->cancelNow();

        $task = self::raiseTask($contract, $services->pluck('name')->all(), $by);

        self::tellTheStaff($contract, $task);

        return $task;
    }

    private static function stopCollection(Contract $contract): void
    {
        if (! $contract->auto_collect && blank($contract->stripe_subscription_id)) {
            return;
        }

        try {
            app(StripeBillingService::class)->cancelSubscription($contract);
        } catch (Throwable $e) {
            // The cancellation still stands; the mandate is chased by hand.
            Log::warning('Stripe subscription cancel failed on contract cancellation', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $serviceNames
     */
    private static function raiseTask(Contract $contract, array $serviceNames, string $by): PlannedTask
    {
        $cancellation = ContractCancellation::for($contract);
        $ends = $cancellation->endsOn();

        $notes = [
            __('Cancelled by :who on :date.', [
                'who' => $by === 'customer' ? __('the customer') : __('an administrator'),
                'date' => now()->translatedFormat('j F Y'),
            ]),
        ];

        if ($ends) {
            $notes[] = __('Runs until :date.', ['date' => $ends->translatedFormat('j F Y')]);
        }

        if ($invoice = $cancellation->lastInvoiceOn()) {
            $notes[] = __('Last invoice :date.', ['date' => $invoice->translatedFormat('j F Y')]);
        }

        if ($serviceNames !== []) {
            $notes[] = __('Services in this contract: :names.', ['names' => implode(', ', $serviceNames)]);
        }

        $notes[] = __('Make sure the licence, hosting or domain behind it is not renewed.');

        return PlannedTask::create([
            'company_id' => $contract->company_id,
            'title' => __('Stop renewal: :contract', ['contract' => $contract->name]),
            'kind' => PlannedTaskKind::Cancellation,
            'status' => PlannedTaskStatus::Planned,
            'priority' => 'high',
            // Due when the service stops, which is the day it has to have
            // been dealt with by.
            'due_on' => $ends?->toDateString() ?? now()->addWeek()->toDateString(),
            'notes' => implode("\n", $notes),
        ]);
    }

    private static function tellTheStaff(Contract $contract, PlannedTask $task): void
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();

        if ($staff->isEmpty()) {
            return;
        }

        Notification::send($staff, new ContractCancelled($contract, $task));
    }
}

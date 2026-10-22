<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractRenewalReminder;
use App\Notifications\ReminderDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class SendRenewalReminders extends Command
{
    protected $signature = 'contracts:send-renewal-reminders
                            {--days=30 : Horizon in days}
                            {--force : Send for everything in the horizon, not only 30/14/7/1}';

    protected $description = 'Send one digest of the contracts whose renewal or notice date is approaching.';

    /** @var list<int> */
    public const DEFAULT_OFFSETS = [30, 14, 7, 1, 0];

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $today = now()->startOfDay();
        $horizon = $today->copy()->addDays($days);

        // Monthly and one-time contracts are skipped: see
        // BillingCycle::withRenewalTerm(). Reminding staff and the customer
        // about a monthly contract every single month is noise, not a warning.
        $contracts = Contract::query()
            ->with(['company.contacts'])
            ->withUpcomingRenewalTerm()
            ->get();

        $recipients = User::query()
            ->whereIn('role', ['admin', 'manager'])
            ->get();

        /** @var Collection<int, Contract> $forStaff */
        $forStaff = new Collection;
        $context = [];

        /** @var array<int, Collection<int, Contract>> $byCompany */
        $byCompany = [];

        foreach ($contracts as $contract) {
            $renewal = $contract->renewal_date->copy()->startOfDay();
            $noticeDeadline = $contract->notice_deadline?->copy()->startOfDay();

            $reason = null;
            $daysUntil = null;

            if ($noticeDeadline && $noticeDeadline->betweenIncluded($today, $horizon)) {
                $reason = 'notice';
                $daysUntil = (int) $today->diffInDays($noticeDeadline);
            } elseif ($renewal->betweenIncluded($today, $horizon)) {
                $reason = 'renewal';
                $daysUntil = (int) $today->diffInDays($renewal);
            }

            if ($reason === null) {
                continue;
            }

            if (! $force && ! in_array($daysUntil, self::DEFAULT_OFFSETS, true)) {
                continue;
            }

            $context[$contract->getKey()] = ['days' => $daysUntil, 'reason' => $reason];
            $forStaff->push($contract);

            // The bell in the panel stays one entry per contract — that is
            // a list you click through, not an inbox you drown in.
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractRenewalReminder($contract, $daysUntil, $reason));
            }

            // A customer hears only about their own.
            if ($contract->shouldNotifyCustomer() && $contract->company_id) {
                $byCompany[$contract->company_id] ??= new Collection;
                $byCompany[$contract->company_id]->push($contract);
            }
        }

        if ($forStaff->isEmpty()) {
            $this->info("Nothing due (horizon: {$days} days).");

            return self::SUCCESS;
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ReminderDigest(ReminderDigest::CONTRACTS, $forStaff, $context));
        }

        $sentCustomers = 0;
        foreach ($byCompany as $theirs) {
            $contacts = $theirs->first()->company?->contacts->filter(fn ($c) => filled($c->email)) ?? collect();
            if ($contacts->isEmpty()) {
                continue;
            }

            Notification::send($contacts, new ReminderDigest(
                ReminderDigest::CONTRACTS, $theirs, $context, forCustomer: true
            ));
            $sentCustomers += $contacts->count();
        }

        $this->info($forStaff->count()." contract(s) digested to {$recipients->count()} staff and {$sentCustomers} customer contact(s) (horizon: {$days} days).");

        return self::SUCCESS;
    }
}

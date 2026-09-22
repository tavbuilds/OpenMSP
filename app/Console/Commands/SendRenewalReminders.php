<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ContractRenewalReminder;
use App\Notifications\CustomerContractReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRenewalReminders extends Command
{
    protected $signature = 'contracts:send-renewal-reminders
                            {--days=30 : Horizon in days}
                            {--force : Send for everything in the horizon, not only 30/14/7/1}';

    protected $description = 'Send reminders for contracts whose renewal or notice date is approaching.';

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

        $sentStaff = 0;
        $sentCustomers = 0;

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

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ContractRenewalReminder(
                    $contract, $daysUntil, $reason
                ));
                $sentStaff++;
            }

            if ($contract->shouldNotifyCustomer()) {
                $contacts = $contract->company?->contacts
                    ?->filter(fn ($c) => filled($c->email))
                    ?? collect();

                if ($contacts->isNotEmpty()) {
                    Notification::send($contacts, new CustomerContractReminder(
                        $contract, $daysUntil, $reason
                    ));
                    $sentCustomers += $contacts->count();
                }
            }
        }

        $this->info("{$sentStaff} staff and {$sentCustomers} customer reminder(s) sent (horizon: {$days} days).");

        return self::SUCCESS;
    }
}

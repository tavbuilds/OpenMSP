<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Notifications\DomainExpiryReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Domain reminders are internal. The customer hears about renewals through
 * their contract; a domain expiring is our job to act on, not theirs.
 */
class SendDomainExpiryReminders extends Command
{
    protected $signature = 'domains:send-expiry-reminders';

    protected $description = 'Send internal domain expiry reminders (30/14/7/1 days and expired).';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();
        if ($staff->isEmpty()) {
            $this->warn('No admin or manager to notify.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach (Domain::query()->with('company')->whereNotNull('expires_at')->get() as $domain) {
            $which = $domain->dueNotification();
            if ($which === null) {
                continue;
            }

            Notification::send($staff, new DomainExpiryReminder($domain, $which));
            $domain->markNotified($which);
            $sent++;
        }

        $this->info("{$sent} domain reminder(s) sent.");

        return self::SUCCESS;
    }
}

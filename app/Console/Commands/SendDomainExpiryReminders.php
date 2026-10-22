<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Notifications\ReminderDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Domain reminders are internal. The customer hears about renewals through
 * their contract; a domain expiring is our job to act on, not theirs.
 */
class SendDomainExpiryReminders extends Command
{
    protected $signature = 'domains:send-expiry-reminders';

    protected $description = 'Send one internal digest of the domains due (30/14/7/1 days and expired).';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();
        if ($staff->isEmpty()) {
            $this->warn('No admin or manager to notify.');

            return self::SUCCESS;
        }

        /** @var Collection<int, Domain> $due */
        $due = new Collection;
        $context = [];

        // A domain the registrar no longer lists is nothing to chase.
        $domains = Domain::query()
            ->with('company')
            ->present()
            ->whereRaw(Domain::ACTIONABLE_DATE.' is not null')
            ->get();

        foreach ($domains as $domain) {
            $which = $domain->dueNotification();
            if ($which === null) {
                continue;
            }

            $due->push($domain);
            $context[$domain->getKey()] = ['days' => $which === 'expired' ? null : (int) $which];

            $domain->markNotified($which);
        }

        if ($due->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        // One mail for the lot: six of these landing in the same minute
        // is how an inbox teaches someone to ignore all six.
        Notification::send($staff, new ReminderDigest(ReminderDigest::DOMAINS, $due, $context));

        $this->info($due->count().' domain(s) in one digest to '.$staff->count().' recipient(s).');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use App\Models\User;
use App\Notifications\ReminderDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class SendEndpointExpiryReminders extends Command
{
    protected $signature = 'endpoints:send-expiry-reminders';

    protected $description = 'Send one digest of the certificates due (30/14/7/1 days and expired), honoring per-monitor toggles.';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();

        /** @var Collection<int, Endpoint> $forStaff */
        $forStaff = new Collection;
        $context = [];

        /** @var array<int, Collection<int, Endpoint>> $byCompany */
        $byCompany = [];

        $endpoints = Endpoint::query()
            ->with(['company.contacts'])
            ->whereNotNull('expires_at')
            ->get();

        foreach ($endpoints as $endpoint) {
            $which = $endpoint->dueNotification();
            if ($which === null) {
                continue;
            }

            $context[$endpoint->getKey()] = ['days' => $which === 'expired' ? null : (int) $which];
            $forStaff->push($endpoint);

            // A customer hears only about their own, so their digest is
            // built per company rather than from the whole run.
            if ($endpoint->notify_customer && $endpoint->company_id) {
                $byCompany[$endpoint->company_id] ??= new Collection;
                $byCompany[$endpoint->company_id]->push($endpoint);
            }

            $endpoint->markNotified($which);
        }

        if ($forStaff->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new ReminderDigest(ReminderDigest::CERTIFICATES, $forStaff, $context));
        }

        $customers = 0;
        foreach ($byCompany as $theirs) {
            $contacts = $theirs->first()->company?->contacts->filter(fn ($c) => filled($c->email)) ?? collect();
            if ($contacts->isEmpty()) {
                continue;
            }

            Notification::send($contacts, new ReminderDigest(
                ReminderDigest::CERTIFICATES, $theirs, $context, forCustomer: true
            ));
            $customers += $contacts->count();
        }

        $this->info($forStaff->count().' certificate(s) digested to '.$staff->count()." staff and {$customers} customer contact(s).");

        return self::SUCCESS;
    }
}

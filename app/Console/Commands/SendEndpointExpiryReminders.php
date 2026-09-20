<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use App\Models\User;
use App\Notifications\EndpointExpiryReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendEndpointExpiryReminders extends Command
{
    protected $signature = 'endpoints:send-expiry-reminders';

    protected $description = 'Send certificate/endpoint reminders (30/14/7/1 days and expired), honoring per-monitor toggles.';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();
        $sent = 0;

        $endpoints = Endpoint::query()
            ->with(['company.contacts'])
            ->whereNotNull('expires_at')
            ->get();

        foreach ($endpoints as $endpoint) {
            $which = $endpoint->dueNotification();
            if ($which === null) {
                continue;
            }

            if ($staff->isNotEmpty()) {
                Notification::send($staff, new EndpointExpiryReminder($endpoint, $which, false));
            }

            if ($endpoint->notify_customer) {
                $contacts = $endpoint->company?->contacts
                    ?->filter(fn ($c) => filled($c->email))
                    ?? collect();
                if ($contacts->isNotEmpty()) {
                    Notification::send($contacts, new EndpointExpiryReminder($endpoint, $which, true));
                }
            }

            $endpoint->markNotified($which);
            $sent++;
        }

        $this->info("{$sent} endpoint reminder(s) sent.");

        return self::SUCCESS;
    }
}

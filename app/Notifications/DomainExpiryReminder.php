<?php

namespace App\Notifications;

use App\Models\Domain;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal only — there is no customer variant on purpose.
 */
class DomainExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Domain $domain,
        public int|string $which,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $domain = $this->domain;
        $platform = PlatformSettings::name();
        $when = $domain->expires_at?->format('M j, Y') ?? 'unknown';

        if ($this->which === 'expired') {
            $subject = "{$platform}: {$domain->name} has expired";
            $line = "The domain {$domain->name} has expired (date {$when}).";
        } else {
            $subject = "{$platform}: {$domain->name} expires in {$this->which} day(s)";
            $line = "The domain {$domain->name} expires in {$this->which} day(s) (date {$when}).";
        }

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($line)
            ->line('Customer: '.($domain->company?->name ?? 'not assigned')
                .' · Auto-renew: '.($domain->auto_renew ? 'on' : 'off'))
            ->action('View domain', url('/admin/domains/'.$domain->getKey()));
    }
}

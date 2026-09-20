<?php

namespace App\Notifications;

use App\Models\Endpoint;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EndpointExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Endpoint $endpoint,
        public int|string $which,
        public bool $forCustomer = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->endpoint;
        $platform = PlatformSettings::name();
        $when = $e->expires_at?->format('M j, Y') ?? 'unknown';
        $host = $e->hostname ?: $e->name;

        if ($this->which === 'expired') {
            $line = "The certificate/endpoint '{$e->name}' ({$host}) has expired (date {$when}).";
            $subject = "{$platform}: {$e->name} has expired";
        } else {
            $line = "The certificate/endpoint '{$e->name}' ({$host}) expires in {$this->which} day(s) (date {$when}).";
            $subject = "{$platform}: {$e->name} expires in {$this->which} day(s)";
        }

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($line);

        if (! $this->forCustomer) {
            $mail->line('Source: '.($e->source?->getLabel() ?? '—').' · Kind: '.($e->kind?->getLabel() ?? '—'))
                ->action('View endpoint', url('/admin/endpoints/'.$e->getKey()));
        } else {
            $mail->line('Contact your administrator if you were not expecting this.');
        }

        return $mail;
    }
}

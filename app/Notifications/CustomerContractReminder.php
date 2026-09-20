<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerContractReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Contract $contract,
        public int $daysUntil,
        public string $reason, // 'renewal' | 'notice'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contract;
        $platform = PlatformSettings::name();
        $when = $c->renewal_date?->format('M j, Y') ?? 'soon';

        $line = $this->reason === 'notice'
            ? "The notice period for '{$c->name}' ends in {$this->daysUntil} day(s). After that the service renews automatically until {$when}."
            : "Your service '{$c->name}' renews in {$this->daysUntil} day(s) (date {$when}).";

        return (new MailMessage)
            ->subject("{$platform}: reminder for {$c->name}")
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($line)
            ->line('Billing: '.($c->billing_cycle?->getLabel() ?? '—').' · '.$c->portalSalePriceFormatted())
            ->action('Open the customer portal', url('/portal'))
            ->line('This message does not include internal cost or license keys.');
    }
}

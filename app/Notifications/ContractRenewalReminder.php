<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractRenewalReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Contract $contract,
        public int $daysUntil,
        public string $reason, // 'renewal' | 'notice'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contract;
        $label = $this->reason === 'notice'
            ? "notice period ends in {$this->daysUntil} day(s)"
            : "renews in {$this->daysUntil} day(s)";

        return (new MailMessage)
            ->subject("Contract due soon: {$c->company?->name} — {$c->name}")
            ->greeting('Reminder')
            ->line("The contract '{$c->name}' for {$c->company?->name} {$label}.")
            ->line('Renewal date: '.($c->renewal_date?->format('M j, Y') ?? 'unknown'))
            ->line('Auto-renew: '.($c->auto_renew ? 'ON' : 'OFF'))
            ->line('Sale value: '.Money::format($c->total_sale, $c->currency))
            ->action('View contract', url('/admin/contracts/'.$c->getKey()));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'contract_id' => $this->contract->getKey(),
            'company' => $this->contract->company?->name,
            'name' => $this->contract->name,
            'days_until' => $this->daysUntil,
            'reason' => $this->reason,
        ];
    }
}

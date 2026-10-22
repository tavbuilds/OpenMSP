<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One entry in the panel's notification bell.
 *
 * The mail went out as part of the daily digest — a list you read once,
 * rather than a dozen separate letters. This is the clickable record
 * that survives it.
 */
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
        return ['database'];
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

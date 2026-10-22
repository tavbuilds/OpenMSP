<?php

namespace App\Notifications;

use App\Models\ContractBillingChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What a direct debit collects is about to change, and the customer has to
 * agree before it does.
 *
 * Their mandate is not at risk and nothing needs re-authorising — it was
 * given to us, not to an amount. Until they answer, the old amount keeps
 * being collected, which is the only one they have agreed to.
 */
class BillingChangePending extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContractBillingChange $change) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Approve the new amount for :contract', [
                'contract' => $this->change->contract?->name,
            ]))
            ->greeting(__('Hello,'))
            ->line($this->change->explanation())
            ->line(__('Your direct debit would go from :previous to :new per period.', [
                'previous' => $this->change->previousFormatted(),
                'new' => $this->change->newFormatted(),
            ]))
            ->line(__('Nothing has changed yet: we keep collecting :previous until you approve the new amount, and your existing mandate stays as it is.', [
                'previous' => $this->change->previousFormatted(),
            ]))
            ->action(__('Review it in the portal'), route('portal.dashboard'));
    }
}

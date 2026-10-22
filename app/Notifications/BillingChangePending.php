<?php

namespace App\Notifications;

use App\Models\ContractBillingChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A change to the customer's contract, waiting on their approval.
 *
 * Their approval is what carries it out, so the mail has to be clear that
 * nothing has happened yet. Their mandate is not at risk and nothing needs
 * re-authorising — it was given to us, not to an amount.
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
            ->subject($this->change->isAddition()
                ? __('Approve an addition to :contract', ['contract' => $this->change->contract?->name])
                : __('Approve a change to :contract', ['contract' => $this->change->contract?->name]))
            ->greeting(__('Hello,'))
            ->line($this->change->explanation())
            ->line(__('Your direct debit would go from :previous to :new per period.', [
                'previous' => $this->change->previousFormatted(),
                'new' => $this->change->newFormatted(),
            ]))
            ->line($this->change->consequence())
            ->line(__('Your existing mandate is used; you do not have to authorise anything again.'))
            ->action(__('Review it in the portal'), route('portal.dashboard'));
    }
}

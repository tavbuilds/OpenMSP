<?php

namespace App\Notifications;

use App\Models\ContractBillingChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Telling the customer what their direct debit will collect from now on.
 *
 * The change has been made — it was theirs to ask for — so this is not a
 * question. It is the warning they are owed before a different sum
 * leaves their account, and it says plainly that their mandate is
 * untouched, because "your direct debit is changing" reads like trouble
 * otherwise.
 */
class BillingAmountChanged extends Notification implements ShouldQueue
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
            ->subject(__('Your direct debit for :contract is changing', [
                'contract' => $this->change->contract?->name,
            ]))
            ->greeting(__('Hello,'))
            ->line($this->change->explanation())
            ->line(__('From the next period your direct debit goes from :previous to :new.', [
                'previous' => $this->change->previousFormatted(),
                'new' => $this->change->newFormatted(),
            ]))
            ->line(__('Nothing is collected differently before then, and your existing mandate stays as it is — there is nothing for you to authorise.'))
            ->line(__('If this is not what you expected, reply to this email and we will sort it out.'))
            ->action(__('See your services'), route('portal.dashboard'));
    }
}

<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A service moved into or out of a contract, and its direct debit could not
 * come with it: the mandate belongs to the agreement it was given for.
 *
 * Told to the customer rather than discovered by them, because the next
 * thing that happens is an invoice they were not expecting to pay by hand.
 */
class DirectDebitStopped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Contract $contract,
        public ?Contract $nowBilledBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Your direct debit for :contract has stopped', ['contract' => $this->contract->name]))
            ->greeting(__('Hello,'))
            ->line(__('We have reorganised :contract, and the direct debit that was set up for it has been stopped.', [
                'contract' => $this->contract->name,
            ]))
            ->line(__('Nothing is owed because of this and no payment has been taken. Until a new mandate is in place you will receive an invoice to pay yourself.'));

        if ($this->nowBilledBy) {
            $mail->line(__('It is now part of :contract. You can set the direct debit up again there.', [
                'contract' => $this->nowBilledBy->name,
            ]));
        }

        return $mail->action(__('Open the portal'), route('portal.dashboard'));
    }
}
